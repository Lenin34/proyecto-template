<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Service\EmailVerificationService;
use App\Service\EntityProxyCleanerService;
use App\Service\TenantManager;
use App\Service\AuthAlertService;
use App\Enum\ErrorCodes\WebAuthErrorCodes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Psr\Log\LoggerInterface;

class SecurityController extends AbstractController
{
    private EmailVerificationService $emailVerificationService;
    private TenantManager $tenantManager;
    private AuthAlertService $authAlertService;
    private EntityProxyCleanerService $proxyCleanerService;
    private LoggerInterface $logger;
    private \App\Service\TenantLogoService $tenantLogoService;

    public function __construct(
        TenantManager $tenantManager,
        AuthAlertService $authAlertService,
        EmailVerificationService $emailVerificationService,
        EntityProxyCleanerService $proxyCleanerService,
        LoggerInterface $logger,
        \App\Service\TenantLogoService $tenantLogoService
    ) {
        $this->tenantManager = $tenantManager;
        $this->authAlertService = $authAlertService;
        $this->emailVerificationService = $emailVerificationService;
        $this->proxyCleanerService = $proxyCleanerService;
        $this->logger = $logger;
        $this->tenantLogoService = $tenantLogoService;
    }

    #[Route('/{dominio}/login', name: 'app_login')]
    public function login(Request $request, string $dominio, AuthenticationUtils $authenticationUtils): Response
    {
        try {
            $this->logger->info("========== SECURITY CONTROLLER: login() called ==========");
            $this->logger->info("[SecurityController] Dominio: {dominio}", ['dominio' => $dominio]);
            $this->logger->info("[SecurityController] Request Method: {method}", ['method' => $request->getMethod()]);
            $this->logger->info("[SecurityController] Request URI: {uri}", ['uri' => $request->getRequestUri()]);

            // Asegurar que la sesión esté iniciada
            if (!$request->hasSession()) {
                throw new \RuntimeException('Session has not been initialized');
            }

            $session = $request->getSession();
            if (!$session->isStarted()) {
                $session->start();
            }

            $this->logger->info("[SecurityController] Tenant set: {tenant}", ['tenant' => $dominio]);

            $error = $authenticationUtils->getLastAuthenticationError();
            $lastUsername = $authenticationUtils->getLastUsername();

            $user = $this->getUser();

            if ($user) {
                $this->logger->info("[SecurityController] User already authenticated", [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail()
                ]);
                $this->logger->warning("[SecurityController] User tried to access login while authenticated - showing login form anyway");
            } else {
                $this->logger->info("[SecurityController] No user authenticated, showing login form");
            }

            $this->logger->info("========== SECURITY CONTROLLER: Rendering login form ==========");

            // Obtener el logo del tenant
            $logoUrl = $this->tenantLogoService->getLogoUrl($dominio);

            return $this->render('security/login.html.twig', [
                'last_username' => $lastUsername,
                'error' => $error,
                'dominio' => $dominio,
                'logoUrl' => $logoUrl,
                'already_authenticated' => $user !== null,
            ]);
        } catch (\Exception $e) {
            $this->logger->error("[SecurityController] ERROR: {error}", ['error' => $e->getMessage()]);
            throw $this->createNotFoundException($e->getMessage());
        }
    }

    #[Route('/{dominio}/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Este método es interceptado por el firewall de Symfony.');
    }

    #[Route('/{dominio}/forget-password', name: 'forget_password')]
    public function forgetPassword(
        string $dominio,
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        \Psr\Log\LoggerInterface $logger
    ): Response {
        try {
            // CRÍTICO: Configurar el tenant ANTES de cualquier operación
            $this->tenantManager->setCurrentTenant($dominio);

            if ($request->isMethod('GET')) {
                return $this->render('login/password-reset-wizard.html.twig', [
                    'dominio' => $dominio
                ]);
            }

            // Si es POST, procesar la solicitud de código
            if ($request->isMethod('POST')) {
                $email = $request->request->get('email');
                
                // CRÍTICO: Usar EntityManager del tenant actual, NO UserRepository
                // UserRepository busca en TODAS las bases de datos
                $em = $this->tenantManager->getEntityManager();
                $user = $em->createQueryBuilder()
                    ->select('u')
                    ->from('App\\Entity\\App\\User', 'u')
                    ->where('u.email = :email')
                    ->setParameter('email', $email)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($user) {
                    $verificationCode = random_int(100000, 999999);
                    try {
                        $result = $this->emailVerificationService->sendVerificationCode($email, $verificationCode, $dominio);
                        if ($result['success']) {
                            $logger->info("Email enviado a $email con código $verificationCode");
                            $session->set('verification_code', $verificationCode);
                            $session->set('reset_email', $email);
                            return $this->authAlertService->createSuccessResponse(
                                'Código enviado correctamente a su email. Ahora puede ingresar el código de verificación.',
                                ['show_verification_form' => true]
                            );
                        } else {
                            $logger->error("Fallo al enviar email a $email: " . ($result['error'] ?? 'Error desconocido'));
                            return $this->authAlertService->handleWebAuthError(
                                WebAuthErrorCodes::PASSWORD_RESET_EMAIL_FAILED,
                                [
                                    'email' => $email,
                                    'error' => $result['error'] ?? 'Error desconocido'
                                ]
                            );
                        }
                    } catch (\Throwable $e) {
                        $logger->critical("EXCEPCIÓN en email: " . $e->getMessage());
                        return $this->authAlertService->handleWebAuthError(
                            WebAuthErrorCodes::PASSWORD_RESET_EMAIL_FAILED,
                            [
                                'email' => $email,
                                'exception' => $e->getMessage()
                            ]
                        );
                    }
                }

                return $this->authAlertService->handleWebAuthError(
                    WebAuthErrorCodes::PASSWORD_RESET_USER_NOT_FOUND,
                    ['email' => $email]
                );
            }

            return $this->authAlertService->handleWebAuthError(
                WebAuthErrorCodes::FORM_VALIDATION_FAILED
            );
        } catch (\Exception $e) {
            // Manejar errores de tenant o sistema
            if (str_contains($e->getMessage(), 'Tenant')) {
                return $this->authAlertService->handleWebAuthError(
                    WebAuthErrorCodes::TENANT_NOT_FOUND,
                    [
                        'dominio' => $dominio,
                        'error' => $e->getMessage()
                    ]
                );
            }

            return $this->authAlertService->handleWebAuthError(
                WebAuthErrorCodes::INTERNAL_SERVER_ERROR,
                ['error' => $e->getMessage()]
            );
        }
    }

    #[Route('/{dominio}/verify-code', name: 'verify_code', methods: ['POST'])]
    public function verifyCode(
        string $dominio,
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        try {
            $this->logger->info("========== VERIFY CODE CALLED ==========");
            $this->logger->info("Dominio: " . $dominio);
            $this->logger->info("Request Method: " . $request->getMethod());
            
            // CRÍTICO: Configurar el tenant ANTES de usar EntityManager
            $this->tenantManager->setCurrentTenant($dominio);

            // ✅ Bloqueo de acceso manual:
            if (!$request->isXmlHttpRequest()) {
                return $this->redirectToRoute('app_login', ['dominio' => $dominio]);
            }

            $step = $request->request->get('step', 'verify_code');
            $verificationCode = $request->request->get('verification_code');
            $storedCode = $session->get('verification_code');
            $email = $session->get('reset_email');

            if (!$storedCode || !$email) {
                return $this->authAlertService->handleWebAuthError(
                    WebAuthErrorCodes::SESSION_EXPIRED,
                    ['email' => $email]
                );
            }

            if ($step === 'verify_code') {
                // Paso 1: Verificar solo el código
                if ($verificationCode != $storedCode) {
                    return $this->authAlertService->handleWebAuthError(
                        WebAuthErrorCodes::VERIFICATION_CODE_INVALID,
                        ['code_provided' => $verificationCode]
                    );
                }

                // Código correcto, permitir continuar al siguiente paso
                return $this->authAlertService->createSuccessResponse(
                    'Código verificado correctamente. Ahora puede establecer su nueva contraseña.',
                    ['step' => 'change_password']
                );

            } elseif ($step === 'change_password') {
                // Paso 2: Cambiar la contraseña
                $newPassword = $request->request->get('new_password');
                $confirmPassword = $request->request->get('confirm_password');

                // Verificar nuevamente el código por seguridad
                if ($verificationCode != $storedCode) {
                    return $this->authAlertService->handleWebAuthError(
                        WebAuthErrorCodes::VERIFICATION_CODE_INVALID,
                        ['step' => 'change_password']
                    );
                }

                // Validar que las contraseñas coincidan
                if ($newPassword !== $confirmPassword) {
                    return $this->authAlertService->handleWebAuthError(
                        WebAuthErrorCodes::PASSWORD_MISMATCH,
                        ['step' => 'change_password']
                    );
                }

                // Validar fortaleza de la contraseña
                if (strlen($newPassword) < 8) {
                    return $this->authAlertService->handleWebAuthError(
                        WebAuthErrorCodes::PASSWORD_TOO_WEAK,
                        ['step' => 'change_password']
                    );
                }

                // CRÍTICO: Usar EntityManager del tenant actual
                $em = $this->tenantManager->getEntityManager();
                $user = $em->createQueryBuilder()
                    ->select('u')
                    ->from('App\\Entity\\App\\User', 'u')
                    ->where('u.email = :email')
                    ->setParameter('email', $email)
                    ->getQuery()
                    ->getOneOrNullResult();
                    
                if (!$user) {
                    return $this->authAlertService->handleWebAuthError(
                        WebAuthErrorCodes::USER_NOT_FOUND,
                        ['email' => $email]
                    );
                }

                // Actualizar la contraseña
                $this->logger->info("Cambiando contraseña para usuario: " . $user->getEmail());
                $this->logger->info("Nueva contraseña (plain): " . $newPassword);

                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $this->logger->info("Contraseña hasheada: " . $hashedPassword);

                $user->setPassword($hashedPassword);
                $this->logger->info("Contraseña establecida en entidad");
                
                // CRÍTICO: Limpiar proxies antes de flush
                $user = $this->proxyCleanerService->cleanAndReloadUser($user, $em);
                $user->setPassword($hashedPassword);

                try {
                    $em->persist($user);
                    $em->flush();
                    $this->logger->info("Contraseña guardada en base de datos para: " . $user->getEmail());

                    // Limpiar la sesión
                    $session->remove('verification_code');
                    $session->remove('reset_email');

                    return $this->authAlertService->createSuccessResponse(
                        'Contraseña actualizada correctamente. Será redirigido al login.',
                        ['redirect' => $this->generateUrl('app_login', ['dominio' => $dominio])]
                    );
                } catch (\Exception $e) {
                    return $this->authAlertService->handleWebAuthError(
                        WebAuthErrorCodes::INTERNAL_SERVER_ERROR,
                        ['error' => $e->getMessage(), 'step' => 'change_password']
                    );
                }
            }

            // Paso no válido
            return $this->authAlertService->handleWebAuthError(
                WebAuthErrorCodes::FORM_VALIDATION_FAILED,
                ['invalid_step' => $step]
            );

        } catch (\Exception $e) {
            $this->logger->critical("ERROR CRÍTICO EN VERIFY CODE: " . $e->getMessage());
            $this->logger->critical($e->getTraceAsString());
            throw $this->createNotFoundException('Tenant not found (Real error logged)');
        }
    }

    #[Route('/{dominio}/load-verify-code-template', name: 'load_verify_code_template')]
    public function loadVerifyCodeTemplate(string $dominio): Response
    {
        try {
            return $this->render('login/verify-code.html.twig', [
                'dominio' => $dominio
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    #[Route('/{dominio}/password-reset-wizard', name: 'password_reset_wizard')]
    public function passwordResetWizard(string $dominio): Response
    {
        try {
            return $this->render('login/password-reset-wizard.html.twig', [
                'dominio' => $dominio
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }
}