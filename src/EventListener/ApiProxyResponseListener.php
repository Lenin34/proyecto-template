<?php

namespace App\EventListener;

use App\Service\EntityProxyCleanerService;
use App\Service\TenantManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * EventListener que maneja automáticamente los problemas de proxy en las respuestas de la API
 */
class ApiProxyResponseListener implements EventSubscriberInterface
{
    private EntityProxyCleanerService $proxyCleanerService;
    private TenantManager $tenantManager;

    public function __construct(
        EntityProxyCleanerService $proxyCleanerService,
        TenantManager $tenantManager
    ) {
        $this->proxyCleanerService = $proxyCleanerService;
        $this->tenantManager = $tenantManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Solo procesar rutas de API
        if (!str_starts_with($request->getPathInfo(), '/api') && 
            !preg_match('/\/[^\/]+\/api/', $request->getPathInfo())) {
            return;
        }

        // Solo procesar respuestas JSON
        if (!$response instanceof JsonResponse) {
            return;
        }

        // Verificar si hay errores relacionados con proxies en la respuesta
        $content = $response->getContent();
        if ($content && (
            str_contains($content, 'Proxies\\__CG__') ||
            str_contains($content, 'entity identifier associated with the UnitOfWork') ||
            str_contains($content, 'proxy')
        )) {
            // Limpiar el EntityManager actual para evitar futuros problemas
            try {
                $this->tenantManager->clearCurrentEntityManager();
            } catch (\Exception $e) {
                // Ignorar errores al limpiar
            }
        }
    }
}
