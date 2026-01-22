<?php

namespace App\Security;

use App\Entity\Master\MasterUser;
use App\Enum\Status;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use App\Service\AuthAlertService;
use App\Service\TenantManager;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\JsonResponse;

class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private UrlGeneratorInterface $urlGenerator;
    private TenantManager $tenantManager;
    private \Psr\Log\LoggerInterface $logger;
    private AuthAlertService $authAlertService;
    private MasterUserProvider $masterUserProvider;
    private UserProvider $userProvider;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        TenantManager $tenantManager,
        \Psr\Log\LoggerInterface $logger,
        AuthAlertService $authAlertService,
        MasterUserProvider $masterUserProvider,
        UserProvider $userProvider
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->tenantManager = $tenantManager;
        $this->logger = $logger;
        $this->authAlertService = $authAlertService;
        $this->masterUserProvider = $masterUserProvider;
        $this->userProvider = $userProvider;
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->getPayload()->getString('email');
        $tenant = $request->attributes->get('dominio');

        // Log para depuración
        $this->logger->info('Login attempt', [
            'email' => $email,
            'tenant' => $tenant,
        ]);

        // Configurar el tenant antes de la autenticación
        $this->tenantManager->setCurrentTenant($tenant);

        if (!$tenant) {
            throw new \LogicException('Tenant no definido en la petición.');
        }

        // Validar el CSRF token
        $csrfToken = $request->getPayload()->getString('_csrf_token');
        $this->logger->info('CSRF Token Debug', [
            'csrf_token_received' => $csrfToken,
            'csrf_token_length' => strlen($csrfToken),
            'request_method' => $request->getMethod(),
            'content_type' => $request->headers->get('Content-Type')
        ]);

        $password = $request->getPayload()->getString('password');
        $this->logger->info('Login credentials', [
            'email' => $email,
            'password_length' => strlen($password),
            'tenant' => $tenant
        ]);

        // Determinar qué provider usar según el tenant
        $userLoader = null;
        if ($tenant === 'Master') {
            $this->logger->info('Using MasterUserProvider for Master tenant');
            $userLoader = function (string $userIdentifier) {
                return $this->masterUserProvider->loadUserByIdentifier($userIdentifier);
            };
        } else {
            $this->logger->info('Using UserProvider for tenant: ' . $tenant);
            
            // Check if user exists and is inactive BEFORE attempting authentication
            try {
                $em = $this->tenantManager->getEntityManager();
                $user = $em->createQueryBuilder()
                    ->select('u')
                    ->from('App\Entity\App\User', 'u')
                    ->where('u.email = :email')
                    ->setParameter('email', $email)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($user && $user->getStatus() === Status::INACTIVE) {
                    $this->logger->warning('Login attempt with inactive account', [
                        'email' => $email,
                        'user_id' => $user->getId()
                    ]);
                    
                    // Store error type in session for specific error message
                    $request->getSession()->set('_security.login.error_type', 'inactive_account');
                    
                    throw new CustomUserMessageAuthenticationException('Tu cuenta está inactiva. Por favor, contacta al administrador.');
                }
            } catch (CustomUserMessageAuthenticationException $e) {
                throw $e;
            } catch (\Exception $e) {
                $this->logger->error('Error checking user status', [
                    'error' => $e->getMessage()
                ]);
            }
            
            $userLoader = function (string $userIdentifier) {
                return $this->userProvider->loadUserByIdentifier($userIdentifier);
            };
        }

        $passport = new Passport(
            new UserBadge($email, $userLoader),
            new PasswordCredentials($password),
            [
                // TEMPORALMENTE DESHABILITADO - PROBLEMA CON CSRF EN MULTI-TENANT
                // new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );

        // ROLE-BASED ACCESS CONTROL: Validate user has required permissions
        // This validation happens after password verification but before login success
        if ($tenant !== 'Master') {
            try {
                // Load the user to check their role
                $user = $userLoader($email);
                
                if ($user) {
                    $roles = $user->getRoles();
                    
                    // Check if user has at least ROLE_LIDER or ROLE_ADMIN
                    $hasRequiredRole = in_array('ROLE_LIDER', $roles) || 
                                      in_array('ROLE_ADMIN', $roles) || 
                                      in_array('ROLE_MASTER_USER', $roles);
                    
                    if (!$hasRequiredRole) {
                        $this->logger->warning('Login attempt with insufficient role permissions', [
                            'email' => $email,
                            'user_id' => $user->getId(),
                            'roles' => $roles
                        ]);
                        
                        // Store error type in session for specific error message
                        $request->getSession()->set('_security.login.error_type', 'insufficient_role');
                        
                        throw new CustomUserMessageAuthenticationException('No tiene los permisos necesarios para acceder al sistema.');
                    }
                }
            } catch (CustomUserMessageAuthenticationException $e) {
                throw $e;
            } catch (\Exception $e) {
                $this->logger->error('Error checking user role permissions', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $passport;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->logger->info('========== LOGIN AUTHENTICATOR: onAuthenticationSuccess START ==========');

        $tenant = $request->attributes->get('dominio');
        $this->logger->info('LOGIN: Tenant from request', ['tenant' => $tenant]);

        $this->tenantManager->setCurrentTenant($tenant);
        
        // Extraer usuario y roles AL PRINCIPIO para tenerlos disponibles
        $user = $token->getUser();
        $email = $user->getEmail();
        $roles = $token->getRoleNames();
        $userClass = get_class($user);
        
        // Asegurar persistencia en sesión explícitamente ANTES de cualquier redirección
        if ($request->hasSession()) {
            $session = $request->getSession();
            $session->set('_tenant', $tenant);
            $this->logger->info('LOGIN: Tenant persisted in session explicitly', ['tenant' => $tenant]);
        }
        
        $this->logger->info('LOGIN: Tenant set in TenantManager', ['tenant' => $tenant]);

        // Verificar si hay un target path guardado
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
        
        // Sanear targetPath: Si es favicon, archivo estático, o ruta de Master en tenant normal, ignorarlo
        if ($targetPath && (
            str_contains($targetPath, 'favicon.ico') || 
            str_ends_with($targetPath, '.css') || 
            str_ends_with($targetPath, '.js') ||
            // /panel solo existe en Master, ignorar si estamos en otro tenant
            (str_contains($targetPath, '/panel') && $tenant !== 'Master')
        )) {
            $this->logger->warning('LOGIN: Ignored invalid target path', ['target' => $targetPath, 'tenant' => $tenant]);
            $targetPath = null;
        }

        if ($targetPath) {
            $this->logger->info('LOGIN: Found saved target path', ['target' => $targetPath]);
            
            // Detectar si es una URL absoluta o una ruta relativa
            $isAbsoluteUrl = str_starts_with($targetPath, 'http://') || str_starts_with($targetPath, 'https://');
            
            if ($isAbsoluteUrl) {
                // Si es URL absoluta, extraer solo la parte del path
                $parsedUrl = parse_url($targetPath);
                $targetPath = $parsedUrl['path'] ?? '/';
                $this->logger->info('LOGIN: Converted absolute URL to path', ['path' => $targetPath]);
            }
            
            // Validar que el targetPath incluya el tenant correcto
            $expectedPrefix = '/' . $tenant . '/';
            
            // Si el targetPath NO incluye el tenant, reconstruirlo
            if (!str_starts_with($targetPath, $expectedPrefix)) {
                $this->logger->warning('LOGIN: Target path missing tenant prefix, reconstructing', [
                    'original_path' => $targetPath,
                    'expected_prefix' => $expectedPrefix
                ]);
                
                // Extraer la parte de la ruta sin tenant (si tiene otro tenant)
                $pathWithoutTenant = preg_replace('#^/[^/]+/#', '', $targetPath);
                
                // Reconstruir con el tenant correcto
                $targetPath = $expectedPrefix . $pathWithoutTenant;
                
                $this->logger->info('LOGIN: Reconstructed target path', ['new_path' => $targetPath]);
            }
            
            $this->logger->info('LOGIN: Redirecting to sanitized target path', ['target' => $targetPath]);
            
            // CRÍTICO: Evitar bucle de "Access Denied" para usuarios sin permisos
            // Si el usuario NO es LIDER ni ADMIN, y trata de ir a rutas administrativas, forzar dashboard
            $isLiderOrAdmin = in_array('ROLE_LIDER', $roles) || in_array('ROLE_ADMIN', $roles) || in_array('ROLE_MASTER_USER', $roles);
            
            if (!$isLiderOrAdmin) {
                // Rutas permitidas para usuarios básicos
                $allowedPaths = ['/dashboard', '/profile'];
                $isAllowed = false;
                
                foreach ($allowedPaths as $path) {
                    if (str_contains($targetPath, $path)) {
                        $isAllowed = true;
                        break;
                    }
                }
                
                if (!$isAllowed) {
                    $this->logger->warning('LOGIN: User lacks permission for target path, forcing dashboard', [
                        'user' => $email,
                        'target' => $targetPath,
                        'roles' => $roles
                    ]);
                    
                    // Agregar mensaje flash para notificar al usuario
                    if ($request->hasSession()) {
                        $request->getSession()->getFlashBag()->add('warning', 'No tienes permisos para acceder a la página solicitada. Has sido redirigido al inicio.');
                    }
                    
                    // No retornamos aquí, dejamos que el flujo continúe y se generará la URL del dashboard abajo
                    $targetPath = null; 
                } else {
                    return new RedirectResponse($targetPath);
                }
            } else {
                return new RedirectResponse($targetPath);
            }
        }

        $this->logger->info('LOGIN: User details', [
            'email' => $email,
            'tenant' => $tenant,
            'user_type' => $userClass,
            'roles' => $roles,
            'is_master_user' => $user instanceof MasterUser
        ]);

        $this->logger->info('LOGIN: User details', [
            'email' => $email,
            'tenant' => $tenant,
            'user_type' => $userClass,
            'roles' => $roles,
            'is_master_user' => $user instanceof MasterUser
        ]);

        // Si es un usuario Master (MasterUser), redirigir al panel Master
        if ($user instanceof MasterUser && $tenant === 'Master') {
            $this->logger->info('LOGIN: Detected MasterUser, preparing redirect to master panel');

            // Actualizar last_login
            $user->setLastLogin(new \DateTime());
            $this->tenantManager->clearAllEntityManagers();
            $this->tenantManager->setCurrentTenant('Master');
            $this->tenantManager->getEntityManager()->flush();

            $masterUrl = $this->urlGenerator->generate('app_master', ['dominio' => 'Master']);
            $this->logger->info('LOGIN: Generated master panel URL', ['url' => $masterUrl]);
            $this->logger->info('========== LOGIN: Redirecting to MASTER PANEL ==========');

            return new RedirectResponse($masterUrl);
        }

        // Para usuarios normales de tenants - ir directo al dashboard
        $this->logger->info('LOGIN: Regular user detected, preparing redirect to dashboard');

        $dashboardUrl = $this->urlGenerator->generate('app_dashboard', ['dominio' => $tenant]);
        $this->logger->info('LOGIN: Generated dashboard URL', [
            'url' => $dashboardUrl,
            'tenant' => $tenant,
            'route' => 'app_dashboard'
        ]);

        $this->logger->info('========== LOGIN: Redirecting to DASHBOARD ==========');

        $response = new RedirectResponse($dashboardUrl);
        $this->logger->info('LOGIN: RedirectResponse created', [
            'target_url' => $response->getTargetUrl(),
            'status_code' => $response->getStatusCode()
        ]);

        return $response;
    }

    protected function getLoginUrl(Request $request): string
    {
        $tenant = $request->attributes->get('dominio');

        // Fallback robusto: si el tenant no está definido o es 'login' (que suele indicar un error en la detección),
        // intentamos extraerlo de la URL actual.
        if (empty($tenant) || $tenant === 'login') {
            $pathInfo = $request->getPathInfo();
            $matches = [];
            // Intenta capturar el primer segmento: /cliente/otracosa -> captura "cliente"
            if (preg_match('#^/([^/]+)/#', $pathInfo, $matches)) {
                $candidate = $matches[1];
                // Ignoramos palabras reservadas del sistema o assets
                if (!in_array($candidate, ['login', 'logout', 'api', 'css', 'js', 'images', 'bundles', '_wdt', '_profiler'])) {
                    $tenant = $candidate;
                }
            }
        }
        
        // Si falló todo, aseguramos un valor por defecto para evitar errores críticos en generate()
        // 'issemym' es el tenant principal del usuario, mejor redirigir ahí que crashear.
        $tenant = $tenant ?: 'issemym';

        return $this->urlGenerator->generate(self::LOGIN_ROUTE, ['dominio' => $tenant]);
    }

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): Response {
        $tenant = $request->attributes->get('dominio');
        $email = $request->getPayload()->getString('email');

        // Si es una petición AJAX, devolver respuesta JSON
        if ($request->isXmlHttpRequest()) {
            return $this->authAlertService->handleAuthenticationError($exception, [
                'email' => $email,
                'tenant' => $tenant,
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);
        }

        // Para formularios tradicionales, redirigir al login con el error
        // Detectar tipo de error y guardarlo en sesión
        $errorType = 'invalid_credentials'; // Default
        
        // Si el mensaje ya indica cuenta inactiva, mantenerlo
        if (!$request->getSession()->has('_security.login.error_type')) {
            $request->getSession()->set('_security.login.error_type', $errorType);
        }

        // CRITICAL FIX: Save the exception to the session so AuthenticationUtils can find it
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        
        $this->logger->warning('Authentication failed', [
            'email' => $email,
            'error_type' => $request->getSession()->get('_security.login.error_type'),
            'exception' => $exception->getMessage()
        ]);

        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE, ['dominio' => $tenant]));
    }

    public function enableDoctrineDebugging()
    {
        // Habilitar el modo de depuración de Doctrine
        $entityManager = $this->tenantManager->getEntityManager();
        $entityManager->getConfiguration()->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger());
    }
}
