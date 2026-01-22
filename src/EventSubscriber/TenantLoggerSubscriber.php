<?php

namespace App\EventSubscriber;

use App\Service\TenantLoggerService;
use App\Service\TenantManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber para manejar la limpieza de caché de loggers cuando cambia el tenant
 */
class TenantLoggerSubscriber implements EventSubscriberInterface
{
    private TenantLoggerService $tenantLogger;
    private TenantManager $tenantManager;
    private ?string $lastTenant = null;

    public function __construct(
        TenantLoggerService $tenantLogger,
        TenantManager $tenantManager
    ) {
        $this->tenantLogger = $tenantLogger;
        $this->tenantManager = $tenantManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Ejecutar después del TenantRequestSubscriber (31) pero antes de otros listeners
            KernelEvents::REQUEST => ['onKernelRequest', 30],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $currentTenant = $request->attributes->get('dominio');

        if (!$currentTenant) {
            return;
        }

        // Si el tenant ha cambiado, limpiar caché de loggers
        if ($this->lastTenant !== null && $this->lastTenant !== $currentTenant) {
            $this->tenantLogger->clearLoggerCache();
        }

        // Establecer el tenant actual en el logger
        $this->tenantLogger->setCurrentTenant($currentTenant);

        $this->lastTenant = $currentTenant;
    }
}
