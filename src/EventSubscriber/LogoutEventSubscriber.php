<?php

namespace App\EventSubscriber;

use App\Service\TenantManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Event Subscriber para manejar logout en contexto multi-tenant
 * Asegura que el usuario sea redirigido al login del tenant correcto
 */
class LogoutEventSubscriber implements EventSubscriberInterface
{
    private UrlGeneratorInterface $urlGenerator;
    private TenantManager $tenantManager;
    private LoggerInterface $logger;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        TenantManager $tenantManager,
        LoggerInterface $logger
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->tenantManager = $tenantManager;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        
        // 1. Intentar obtener el tenant de múltiples fuentes
        $tenant = $this->resolveTenant($request);

        $this->logger->info('LOGOUT: User logged out', [
            'tenant' => $tenant,
            'session_id' => $request->getSession()->getId()
        ]);

        // 2. Generar URL de login con el tenant correcto
        if ($tenant && $this->tenantManager->isValidTenant($tenant)) {
            $loginUrl = $this->urlGenerator->generate('app_login', ['dominio' => $tenant]);
        } else {
            // Fallback: usar tenant por defecto
            $defaultTenant = 'ts'; // o el que uses como default
            $loginUrl = $this->urlGenerator->generate('app_login', ['dominio' => $defaultTenant]);
            
            $this->logger->warning('LOGOUT: No valid tenant found, using default', [
                'default_tenant' => $defaultTenant
            ]);
        }

        $this->logger->info('LOGOUT: Redirecting to login', ['url' => $loginUrl]);

        // 3. Establecer la respuesta de redirección
        $event->setResponse(new RedirectResponse($loginUrl));
    }

    private function resolveTenant($request): ?string
    {
        // A. Prioridad 1: Parámetro de ruta
        $tenant = $request->attributes->get('dominio');
        if ($tenant) {
            return $tenant;
        }

        // B. Prioridad 2: Sesión (antes de que se limpie)
        if ($request->hasSession()) {
            $session = $request->getSession();
            $tenant = $session->get('_tenant');
            if ($tenant) {
                return $tenant;
            }
        }

        // C. Prioridad 3: TenantManager (puede tener el tenant actual)
        $tenant = $this->tenantManager->getCurrentTenant();
        if ($tenant) {
            return $tenant;
        }

        return null;
    }
}
