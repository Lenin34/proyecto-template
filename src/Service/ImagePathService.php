<?php
namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Enum\ErrorCodes\ImagePathErrorCodes;
use App\Service\ApplicationErrorService;
use Psr\Log\LoggerInterface;

class ImagePathService
{
    private RequestStack $requestStack;
    private ApplicationErrorService $applicationErrorService;
    private ParameterBagInterface $params;
    private LoggerInterface $logger;

    public function __construct(
        RequestStack $requestStack,
        ApplicationErrorService $applicationErrorService,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->applicationErrorService = $applicationErrorService;
        $this->requestStack = $requestStack;
        $this->params = $params;
        $this->logger = $logger;
    }

    public function generateFullPath(?string $relativePath): ?string
    {
        $this->logger->info('[ImagePathService] generateFullPath called', [
            'relative_path' => $relativePath
        ]);

        if (!$relativePath) {
            $this->logger->warning('[ImagePathService] No relative path provided');
            $this->applicationErrorService->createError(ImagePathErrorCodes::IMAGE_PATH_SERVICE_NO_RELATIVE_PATH);
            return null;
        }

        $env = $this->params->get('app.env');

        $this->logger->info('[ImagePathService] Environment info', [
            'env' => $env
        ]);

        // SIEMPRE usar las variables de entorno configuradas para garantizar URLs correctas
        // Esto evita problemas con proxies/balanceadores de carga que pueden devolver HTTP en lugar de HTTPS
        if ($env === 'dev') {
            $baseUrl = $this->params->get('app.url.dev');
            $this->logger->info('[ImagePathService] Using dev URL from config', ['base_url' => $baseUrl]);
        } else {
            // En producción, SIEMPRE usar la URL configurada (con HTTPS)
            $baseUrl = $this->params->get('app.url.prod');
            $this->logger->info('[ImagePathService] Using prod URL from config', ['base_url' => $baseUrl]);

            // Fallback si no hay configuración
            if (!$baseUrl) {
                $baseUrl = 'https://sindicato.grupooptimo.mx';
                $this->logger->warning('[ImagePathService] Using fallback URL', ['base_url' => $baseUrl]);
            }
        }

        $fullUrl = $baseUrl . '/uploads/' . $relativePath;
        $this->logger->info('[ImagePathService] Generated full URL', [
            'full_url' => $fullUrl,
            'base_url' => $baseUrl,
            'relative_path' => $relativePath,
            'protocol' => parse_url($fullUrl, PHP_URL_SCHEME)
        ]);

        return $fullUrl;
    }
}