<?php

namespace App\Controller\Api;

use App\Entity\App\User;
use App\Enum\Status;
use App\Service\AutoProxyCleanupService;
use App\Service\ErrorResponseService;
use App\Service\ImagePathService;
use App\Service\TenantConfigurationService;
use App\Service\TenantManager;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/{dominio}/api')]
class GoogleLoginController extends AbstractController
{
    public function __construct(
        private TenantManager $tenantManager,
        private ErrorResponseService $errorResponseService,
        private JWTTokenManagerInterface $jwtTokenManager,
        private TenantConfigurationService $tenantConfigurationService,
        private AutoProxyCleanupService $autoProxyCleanupService,
        private ImagePathService $imagePathService,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {}

    #[Route('/google_login', name: 'api_google_login', methods: ['POST'])]
    public function googleLogin(string $dominio, Request $request): JsonResponse
    {
        try {
            // Establecer el tenant explícitamente
            $this->tenantManager->setCurrentTenant($dominio);
            
            $data = json_decode($request->getContent(), true);
            $idToken = $data['token'] ?? null;
            $platform = $data['platform'] ?? 'unknown';

            if (!$idToken) {
                return new JsonResponse([
                    'error' => true,
                    'msg' => 'Token no proporcionado'
                ], 400);
            }

            // 1. Validar el JWT (ID Token de Google)
            $payload = $this->verifyGoogleIdToken($idToken);
            
            if (!$payload) {
                return new JsonResponse([
                    'error' => true,
                    'msg' => 'Token de Google inválido o expirado'
                ], 401);
            }

            $email = $payload['email'] ?? null;
            if (!$email) {
                return new JsonResponse([
                    'error' => true,
                    'msg' => 'El token no contiene un email válido'
                ], 400);
            }

            $googleId = $payload['sub'] ?? null;
            
            $this->logger->info('🔍 Google Login - Datos recibidos', [
                'email' => $email,
                'googleId' => $googleId
            ]);

            // 2. Buscar usuario
            $em = $this->tenantManager->getEntityManager();
            
            // 2.1 Intentar buscar por ID de Google (ya vinculado)
            $user = null;
            if ($googleId) {
                $this->logger->info('🔍 Buscando por google_auth', [
                    'googleId' => $googleId,
                    'googleId_type' => gettype($googleId),
                    'googleId_length' => strlen($googleId)
                ]);
                
                // Verificar la base de datos actual
                $dbName = $em->getConnection()->getDatabase();
                $this->logger->info('📊 Base de datos actual', ['database' => $dbName]);
                
                // Intentar con consulta directa primero para debugging
                try {
                    $directQuery = $em->createQuery(
                        'SELECT u FROM App\\Entity\\App\\User u 
                         WHERE u.google_auth = :googleId 
                         AND u.status = :status'
                    )
                        ->setParameter('googleId', $googleId)
                        ->setParameter('status', Status::ACTIVE)
                        ->getOneOrNullResult();
                    
                    if ($directQuery) {
                        $this->logger->info('✅ Usuario encontrado con consulta directa', [
                            'userId' => $directQuery->getId(),
                            'email' => $directQuery->getEmail(),
                            'google_auth' => $directQuery->getGoogleAuth()
                        ]);
                        $user = $directQuery;
                    } else {
                        $this->logger->warning('⚠️ NO encontrado con consulta directa');
                    }
                } catch (\Exception $e) {
                    $this->logger->error('❌ Error en consulta directa: ' . $e->getMessage());
                }
                
                if (!$user) {
                    // Intentar con findOneBy como fallback
                    $user = $em->getRepository(User::class)->findOneBy([
                        'google_auth' => $googleId,
                        'status' => Status::ACTIVE
                    ]);
                    
                    if ($user) {
                        $this->logger->info('✅ Usuario encontrado por google_auth (findOneBy)', [
                            'userId' => $user->getId(),
                            'email' => $user->getEmail(),
                            'google_auth_db' => $user->getGoogleAuth()
                        ]);
                    } else {
                        $this->logger->warning('⚠️ NO encontrado por google_auth (findOneBy)', [
                            'googleId' => $googleId
                        ]);
                    }
                }
            }

            // 2.2 Si no se encontró por ID, intentar por email (auto-vincular)
            // IMPORTANTE: Esto solo funciona si el email de Google coincide con el email registrado
            if (!$user && $email) {
                $this->logger->info('🔍 Buscando por email para auto-vincular', ['email' => $email]);
                
                $user = $em->createQuery(
                    'SELECT u, c 
                     FROM App\\Entity\\App\\User u 
                     LEFT JOIN u.company c 
                     WHERE u.email = :email 
                         AND u.status = :status'
                )
                    ->setParameter('email', $email)
                    ->setParameter('status', Status::ACTIVE)
                    ->getOneOrNullResult();

                if ($user && $googleId) {
                    // Vincular automáticamente
                    $this->logger->info('🔗 Auto-vinculando cuenta de Google', [
                        'userId' => $user->getId(),
                        'googleId' => $googleId
                    ]);
                    
                    $user->setGoogleAuth($googleId);
                    $user->setVerified(true);
                    $em->flush();
                }
            }

            // 3. Si no existe
            if (!$user) {
                $this->logger->warning('❌ Usuario no encontrado', [
                    'email' => $email,
                    'googleId' => $googleId
                ]);
                
                return new JsonResponse([
                    'error' => true,
                    'msg' => 'Usuario no encontrado. El correo ' . $email . ' no está registrado.'
                ], 404);
            }

            // 4. Si existe, generar token de nuestra app y devolver info
            return $this->autoProxyCleanupService->safeUserOperation($user, $em, function($cleanUser) use ($em, $dominio) {
                // Actualizar última vez visto
                $cleanUser->setLastSeen(new \DateTimeImmutable());
                $this->autoProxyCleanupService->safePersistAndFlush($em, $cleanUser);

                // Generar nuestro JWT
                $appToken = $this->jwtTokenManager->create($cleanUser);

                // Obtener configuración del tenant
                $configuration = $this->tenantConfigurationService->getCurrentModules($dominio);
                
                // Obtener ID de empresa de forma segura
                $companyId = $this->autoProxyCleanupService->safeGetUserCompanyId($cleanUser, $em);
                $companyName = $cleanUser->getCompany() ? $cleanUser->getCompany()->getName() : 'Mi Empresa';

                return new JsonResponse([
                    'token' => $appToken,
                    'user' => [
                        'user_id' => $cleanUser->getId(),
                        'email' => $cleanUser->getEmail(),
                        'name' => $cleanUser->getName(),
                        'last_name' => $cleanUser->getLastName(),
                        'phone_number' => $cleanUser->getPhoneNumber(),
                        'curp' => $cleanUser->getCurp(),
                        'company_id' => $companyId,
                        'company_name' => $companyName,
                    ],
                    'configuration' => $configuration,
                ], 200);
            });

        } catch (\Exception $e) {
            $this->logger->error('Error en Google Login: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'dominio' => $dominio
            ]);

            return new JsonResponse([
                'error' => true,
                'msg' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Verifica el token de Google usando las claves públicas oficiales
     */
    private function verifyGoogleIdToken(string $idToken): ?array
    {
        try {
            // Obtener claves públicas de Google
            // En producción se recomienda cachear esto
            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/oauth2/v3/certs');
            $jwks = $response->toArray();

            // Google ID Tokens son firmados con RS256
            $keys = JWK::parseKeySet($jwks);

            // Decodificar y verificar
            $payload = (array) JWT::decode($idToken, $keys);

            return $payload;
        } catch (\Exception $e) {
            $this->logger->warning('Fallo al verificar token de Google: ' . $e->getMessage());
            return null;
        }
    }
}
