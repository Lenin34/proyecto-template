<?php

namespace App\EventListener;

use App\Service\TenantManager;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

/**
 * SessionListener
 *
 * Este listener asegura que el tenant se persista correctamente en la sesión.
 *
 * IMPORTANTE: NO cambiamos el nombre de la sesión dinámicamente porque esto
 * causa pérdida de sesión entre requests (la cookie se crea con un nombre
 * y PHP busca con otro nombre diferente).
 *
 * El nombre de sesión se configura de forma fija en framework.yaml
 */
class SessionListener implements EventSubscriberInterface
{
    private TenantManager $tenantManager;
    private ?LoggerInterface $logger;

    public function __construct(TenantManager $tenantManager, ?LoggerInterface $logger = null)
    {
        $this->tenantManager = $tenantManager;
        $this->logger = $logger;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Ignorar requests sin sesión o rutas estáticas
        if (!$request->hasSession()) {
            return;
        }

        // Leer el tenant de la ruta; como fallback, de la sesión directamente
        $tenant = $request->attributes->get('dominio');
        if (!$tenant) {
            $session = $request->getSession();
            if ($session && $session->isStarted()) {
                $tenant = $session->get('_tenant') ?? $session->get('tenant') ?? $session->get('current_tenant');
            }
        }

        // Si tenemos un tenant válido, asegurarnos de que esté en la sesión
        if ($tenant && $this->tenantManager->isValidTenant($tenant)) {
            $session = $request->getSession();

            // Solo persistir si la sesión ya está iniciada (no forzar inicio)
            if ($session->isStarted()) {
                $currentSessionTenant = $session->get('_tenant');
                if ($currentSessionTenant !== $tenant) {
                    $session->set('_tenant', $tenant);

                    if ($this->logger) {
                        $this->logger->debug('SessionListener: Tenant actualizado en sesión', [
                            'tenant' => $tenant,
                            'previous' => $currentSessionTenant,
                            'session_id' => $session->getId()
                        ]);
                    }
                }
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Ejecutar después del enrutador (32) y del TenantRequestSubscriber (31)
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }
}
