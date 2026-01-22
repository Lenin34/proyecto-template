<?php
namespace App\Controller\Api;


use App\DTO\Auth\ChangePasswordRequest;
use App\DTO\Auth\LoginRequest;
use App\DTO\Auth\RegistrationRequest;
use App\Entity\App\User;
use App\Enum\ErrorCodes\Api\AuthErrorCodes;
use App\Enum\Status;
use App\Service\AutoProxyCleanupService;
use App\Service\EntityProxyCleanerService;
use App\Service\ErrorResponseService;
use App\Service\ImagePathService;
use App\Service\PhoneVerificationService;
use App\Service\RequestValidatorService;
use App\Service\TenantConfigurationService;
use App\Service\TenantManager;
use App\Service\TwilioWhatsAppService;
use App\Traits\ProxyHandlerTrait;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{dominio}/api')]




class AuthController extends AbstractController
{
    use ProxyHandlerTrait;
    private UserPasswordHasherInterface $passwordHasher;
    private PhoneVerificationService $phoneVerificationService;
    private ErrorResponseService $errorResponseService;
    private RequestValidatorService $requestValidatorService;
    private TenantManager $tenantManager;
    private JWTTokenManagerInterface $jwtTokenManager;
    private EntityProxyCleanerService $proxyCleanerService;
    private AutoProxyCleanupService $autoProxyCleanupService;
    private TwilioWhatsAppService $twilioWhatsAppService;
    private ImagePathService $imagePathService;
    private LoggerInterface $logger;

    private TenantConfigurationService $tenantConfigurationService;

    public function __construct(
        UserPasswordHasherInterface $passwordHasher,
        PhoneVerificationService $phoneVerificationService,
        ErrorResponseService $errorResponseService,
        TenantManager $tenantManager,
        JWTTokenManagerInterface $jwtTokenManager,
        RequestValidatorService $requestValidatorService,
        EntityProxyCleanerService $proxyCleanerService,
        AutoProxyCleanupService $autoProxyCleanupService,
        TwilioWhatsAppService $twilioWhatsAppService,
        ImagePathService $imagePathService,
        LoggerInterface $logger,
        TenantConfigurationService $tenantConfigurationService
    ) {
        $this->passwordHasher = $passwordHasher;
        $this->phoneVerificationService = $phoneVerificationService;
        $this->errorResponseService = $errorResponseService;
        $this->tenantManager = $tenantManager;
        $this->jwtTokenManager = $jwtTokenManager;
        $this->requestValidatorService = $requestValidatorService;
        $this->proxyCleanerService = $proxyCleanerService;
        $this->autoProxyCleanupService = $autoProxyCleanupService;
        $this->twilioWhatsAppService = $twilioWhatsAppService;
        $this->imagePathService = $imagePathService;
        $this->logger = $logger;
        $this->tenantConfigurationService = $tenantConfigurationService;
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(string $dominio, Request $request, RequestValidatorService $requestValidator): JsonResponse
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            $registrationRequest = $requestValidator->validateAndMap($request, RegistrationRequest::class);
            if ($registrationRequest instanceof JsonResponse) {
                return $registrationRequest;
            }

            // Buscar usuario activo y no verificado usando consulta directa
            $userExists = $em->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\App\User', 'u')
                ->where('u.curp = :curp')
                ->andWhere('u.status = :status')
                ->setParameter('curp', $registrationRequest->curp)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$userExists) {
                return $this->errorResponseService->createErrorResponse(AuthErrorCodes::AUTH_USER_NOT_FOUND_OR_INACTIVE,
                    [
                        'curp' => $registrationRequest->curp
                    ]
                );
            }

            // Verificar email solo en el tenant actual usando consulta directa
            $emailExists = $em->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\App\User', 'u')
                ->where('u.email = :email')
                ->andWhere('u.status = :status')
                ->andWhere('u.verified = :verified')
                ->setParameter('email', $registrationRequest->email)
                ->setParameter('status', Status::ACTIVE)
                ->setParameter('verified', true)
                ->getQuery()
                ->getOneOrNullResult();

            if ($emailExists) {
                return $this->errorResponseService->createErrorResponse(AuthErrorCodes::AUTH_EMAIL_ALREADY_REGISTERED,
                    [
                        'email' => $registrationRequest->email
                    ]
                );
            }

            // Verificar teléfono solo en el tenant actual usando consulta directa
            $normalizedPhone = $this->normalizePhoneNumber($registrationRequest->phone_number);
            
            $phoneExists = $em->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\App\User', 'u')
                ->where('u.phone_number = :phone_number')
                ->andWhere('u.status = :status')
                ->setParameter('phone_number', $normalizedPhone)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if ($phoneExists && $phoneExists->getId() !== $userExists->getId()) {
                return $this->errorResponseService->createErrorResponse(AuthErrorCodes::AUTH_PHONE_ALREADY_REGISTERED,
                    [
                        'phone_number' => $registrationRequest->phone_number
                    ]
                );
            }


            $cleanUser = $this->proxyCleanerService->cleanAndReloadUser($userExists, $em);


            $user = $this->updateUser($registrationRequest, $cleanUser);


            $verificationCode = random_int(100000, 999999);
            $user->setVerificationCode((string)$verificationCode);


            $em->persist($user);
            $em->flush();


            $this->logger->info('Usuario registrado exitosamente', [
                'user_id' => $user->getId(),
                'phone_number' => $user->getPhoneNumber(),
                'verification_code_generated' => $verificationCode
            ]);

            return new JsonResponse([
                'message' => 'Usuario registrado exitosamente.',
                'user_id' => $user->getId(),
                'code' => 201,
                'next_step' => 'call_verify_phone_number_endpoint'
            ], 201);

        } catch (\Exception $e) {
            // Log del error específico
            $this->logger->error('Error en registro de usuario', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'dominio' => $dominio
            ]);

            if (str_contains($e->getMessage(), 'Proxies\\__CG__') ||
                str_contains($e->getMessage(), 'entity identifier associated with the UnitOfWork')) {
                try {
                    $this->tenantManager->clearCurrentEntityManager();
                } catch (\Exception $clearException) {
                    // Ignorar errores al limpiar
                }
            }

            return $this->errorResponseService->createErrorResponse(AuthErrorCodes::AUTH_REGISTRATION_FAILED,
                [
                    'message' => 'Error interno durante el registro. Por favor intente nuevamente.',
                    'messages' => $e->getTrace()
                ]
            );
        }
    }

    #[Route('/users/{userId}/password', name: 'api_change_password', methods: ['PATCH'])]
    public function changePassword(string $dominio, int $userId, Request $request, RequestValidatorService $requestValidator): JsonResponse
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            $changePasswordRequest = $requestValidator->validateAndMap($request, ChangePasswordRequest::class);
            if ($changePasswordRequest instanceof JsonResponse) {
                return $changePasswordRequest;
            }

            $user = $em->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\App\User', 'u')
                ->where('u.id = :id')
                ->andWhere('u.status = :status')
                ->setParameter('id', $userId)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();
            if (!$user) {
                return $this->errorResponseService->createErrorResponse(AuthErrorCodes::AUTH_USER_NOT_FOUND_OR_INACTIVE,
                    [
                        'user_id' => $userId
                    ]
                );
            }

            // Limpiar y recargar la entidad User para evitar problemas de proxy
            $user = $this->proxyCleanerService->cleanAndReloadUser($user, $em);

            if (!$this->passwordHasher->isPasswordValid($user, $changePasswordRequest->current_password)) {
                return $this->errorResponseService->createErrorResponse(AuthErrorCodes::AUTH_USER_INCORRECT_PASSWORD,
                    [
                        'user_id' => $userId
                    ]
                );
            }

            $passwordHashed = $this->passwordHasher->hashPassword($user, $changePasswordRequest->new_password);
            $user->setPassword($passwordHashed);
            $user->setLastSeen(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTimeImmutable());

            $em->persist($user);
            $em->flush();

            return new JsonResponse([
                'message' => 'Contraseña cambiada con éxito.',
                'code' => 200,
            ], 200);

        } catch (\Exception $e) {
            // Manejar errores de proxy específicamente
            $this->clearEntityManagerOnProxyError($e, $em);

            // Si es un error de proxy, devolver un error más específico
            if (str_contains($e->getMessage(), 'Proxies\\__CG__') ||
                str_contains($e->getMessage(), 'entity identifier associated with the UnitOfWork')) {

                return $this->errorResponseService->createErrorResponse(AuthErrorCodes::AUTH_INTERNAL_ERROR,
                    [
                        'message' => 'Error interno del sistema. Por favor intente nuevamente.'
                    ]
                );
            }

            // Re-lanzar otros errores
            throw $e;
        }
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(string $dominio, Request $request, RequestValidatorService $requestValidator): JsonResponse
    {

        $em = $this->tenantManager->getEntityManager();

        $loginRequest = $requestValidator->validateAndMap($request, LoginRequest::class);
        if ($loginRequest instanceof JsonResponse) {
            return $loginRequest;
        }

        if (isset($loginRequest->email)) {
            $user = $em->createQuery(
                'SELECT u, c 
                 FROM App\\Entity\\App\\User u 
                 LEFT JOIN u.company c 
                 WHERE u.email = :email 
                     AND u.status = :status 
                     AND u.verified = :verified'
            )
                ->setParameter('email', $loginRequest->email)
                ->setParameter('status', Status::ACTIVE)
                ->setParameter('verified', Status::ACTIVE)
                ->getOneOrNullResult();
        } elseif (isset($loginRequest->phone_number)) {
            $user = $em->createQuery(
                'SELECT u, c 
                 FROM App\\Entity\\App\\User u 
                 LEFT JOIN u.company c 
                 WHERE u.phone_number = :phone_number 
                     AND u.status = :status 
                     AND u.verified = :verified'
            )
                ->setParameter('phone_number', $loginRequest->phone_number)
                ->setParameter('status', Status::ACTIVE)
                ->setParameter('verified', Status::ACTIVE)
                ->getOneOrNullResult();
        }

        if (!$user) {
            return $this->errorResponseService->createErrorResponse(AuthErrorCodes::AUTH_USER_NOT_FOUND_OR_INACTIVE,
                [
                    'email' => $loginRequest->email
                ]
            );
        }

        if (!$this->passwordHasher->isPasswordValid($user, $loginRequest->password)) {
            return $this->errorResponseService->createErrorResponse(AuthErrorCodes::AUTH_USER_INCORRECT_PASSWORD,
                [
                    'email' => $loginRequest->email
                ]
            );
        }

        // Usar el servicio automático para manejar el usuario de forma segura
        return $this->autoProxyCleanupService->safeUserOperation($user, $em, function($cleanUser) use ($em) {
            // Update last seen
            $cleanUser->setLastSeen(new \DateTimeImmutable());

            // Persistir y hacer flush de forma segura
            $this->autoProxyCleanupService->safePersistAndFlush($em, $cleanUser);

            // Generate JWT token
            $token = $this->jwtTokenManager->create($cleanUser);

            // Obtener company_id de forma segura
            $companyId = $this->autoProxyCleanupService->safeGetUserCompanyId($cleanUser, $em);

            $dominio = $this->tenantManager->getCurrentTenant();

            $configuration = $this->tenantConfigurationService->getCurrentModules($dominio);

            return new JsonResponse([
                'token' => $token,
                'user_id' => $cleanUser->getId(),
                'company_id' => $companyId,
                'user' => [
                    'user_id' => $cleanUser->getId(),
                    'email' => $cleanUser->getEmail(),
                    'name' => $cleanUser->getName(),
                    'last_name' => $cleanUser->getLastName(),
                    'photo' => $this->imagePathService->generateFullPath($cleanUser->getPhoto()),
                    'phone_number' => $cleanUser->getPhoneNumber(),
                    'company_id' => $companyId,
                ],
                'configuration' => $configuration,
            ]);
        });
    }



    private function updateUser(RegistrationRequest $registrationRequest, User $user): User
    {
        /*$user->setCurp($registrationRequest->curp)*/;
        $user->setEmail($registrationRequest->email);
        
        // Normalizar teléfono antes de guardar
        $normalizedPhone = $this->normalizePhoneNumber($registrationRequest->phone_number);
        $user->setPhoneNumber($normalizedPhone);
        
        /*$user->setStatus(Status::ACTIVE);*/
        $user->setUpdatedAt(new \DateTimeImmutable());
        $user->setLastSeen(new \DateTimeImmutable());
        /*$user->setEmployeeNumber($registrationRequest->employee_number);*/

        // Extraer y asignar fecha de nacimiento desde el CURP
        $birthday = $this->extractBirthdayFromCurp($registrationRequest->curp);
        if ($birthday !== null) {
            $user->setBirthday($birthday);
        }

        $passwordHashed = $this->passwordHasher->hashPassword($user, $registrationRequest->password);
        $user->setPassword($passwordHashed);

        return $user;
    }

    /**
     * Normaliza el número de teléfono removiendo espacios, guiones y paréntesis
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        // Remover espacios, guiones, paréntesis
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $phoneNumber);

        // Si empieza con +52, removerlo (código de país de México)
        if (str_starts_with($cleaned, '+52')) {
            $cleaned = substr($cleaned, 3);
        }

        // Si empieza con 52 y tiene más de 10 dígitos, remover el 52
        if (str_starts_with($cleaned, '52') && strlen($cleaned) > 10) {
            $cleaned = substr($cleaned, 2);
        }

        return $cleaned;
    }

    /**
     * Extrae la fecha de nacimiento del CURP mexicano
     * Formato CURP: AAAA######HHHHHH## (18 caracteres)
     * Posiciones 4-9 contienen la fecha: AAMMDD
     * 
     * @param string $curp CURP del usuario
     * @return \DateTimeInterface|null Fecha de nacimiento o null si no se puede extraer
     */
    private function extractBirthdayFromCurp(string $curp): ?\DateTimeInterface
    {
        try {
            // Validar que el CURP tenga el formato correcto (18 caracteres)
            if (strlen($curp) !== 18) {
                $this->logger->warning('CURP inválido: longitud incorrecta', ['curp' => $curp]);
                return null;
            }

            // Extraer los 6 dígitos de fecha (AAMMDD) de las posiciones 4-9
            $dateString = substr($curp, 4, 6);

            // Validar que sean dígitos
            if (!ctype_digit($dateString)) {
                $this->logger->warning('CURP inválido: fecha no contiene solo dígitos', ['curp' => $curp]);
                return null;
            }

            // Separar año, mes y día
            $year = (int)substr($dateString, 0, 2);
            $month = (int)substr($dateString, 2, 2);
            $day = (int)substr($dateString, 4, 2);

            // Determinar el siglo (AA < 50 = 20XX, AA >= 50 = 19XX)
            $fullYear = $year < 50 ? 2000 + $year : 1900 + $year;

            // Validar que la fecha sea válida
            if (!checkdate($month, $day, $fullYear)) {
                $this->logger->warning('CURP inválido: fecha no válida', [
                    'curp' => $curp,
                    'year' => $fullYear,
                    'month' => $month,
                    'day' => $day
                ]);
                return null;
            }

            // Crear objeto DateTime
            $birthday = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $fullYear, $month, $day));

            $this->logger->info('Fecha de nacimiento extraída del CURP', [
                'curp' => $curp,
                'birthday' => $birthday->format('Y-m-d')
            ]);

            return $birthday;

        } catch (\Exception $e) {
            $this->logger->error('Error al extraer fecha de nacimiento del CURP', [
                'curp' => $curp,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}