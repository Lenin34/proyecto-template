<?php

namespace App\EventListener;

use App\Service\TenantManager;
use App\Service\EntityProxyCleanerService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;

/**
 * EventListener que maneja automáticamente los errores de proxy en toda la aplicación
 */
class ProxyErrorHandler implements EventSubscriberInterface
{
    private TenantManager $tenantManager;
    private EntityProxyCleanerService $proxyCleanerService;
    private LoggerInterface $logger;

    public function __construct(
        TenantManager $tenantManager,
        EntityProxyCleanerService $proxyCleanerService,
        LoggerInterface $logger
    ) {
        $this->tenantManager = $tenantManager;
        $this->proxyCleanerService = $proxyCleanerService;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Solo manejar errores de proxy
        if (!$this->isProxyError($exception)) {
            return;
        }

        // Log del error de proxy con más detalles
        $this->logger->error('PROXY ERROR DETAILS', [
            'exception' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'url' => $request->getUri(),
            'method' => $request->getMethod(),
            'route' => $request->attributes->get('_route'),
            'tenant' => $request->attributes->get('dominio'),
            'trace' => $exception->getTraceAsString()
        ]);

        // Limpiar EntityManager para evitar futuros problemas
        try {
            $this->tenantManager->clearCurrentEntityManager();
        } catch (\Exception $e) {
            // Ignorar errores al limpiar
        }

        // Si es una ruta de API, devolver respuesta JSON
        if ($this->isApiRoute($request)) {
            $response = new JsonResponse([
                'error' => 'Error interno del sistema',
                'message' => 'Se ha producido un error temporal. Por favor intente nuevamente.',
                'code' => 500
            ], 500);

            $event->setResponse($response);
        }
        // Para rutas web, dejar que Symfony maneje el error normalmente
    }

    /**
     * Verifica si la excepción es un error de proxy
     */
    private function isProxyError(\Throwable $exception): bool
    {
        $message = $exception->getMessage();
        
        return str_contains($message, 'Proxies\\__CG__') ||
               str_contains($message, 'entity identifier associated with the UnitOfWork') ||
               str_contains($message, 'proxy') && str_contains($message, 'Doctrine') ||
               str_contains($message, 'EntityManager is closed') ||
               str_contains($message, 'No entity manager defined');
    }

    /**
     * Verifica si la ruta es una ruta de API
     */
    private function isApiRoute($request): bool
    {
        $path = $request->getPathInfo();
        
        return str_starts_with($path, '/api') || 
               preg_match('/\/[^\/]+\/api/', $path);
    }
}
