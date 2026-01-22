<?php

namespace App\Controller\Api;

use App\Service\ImagePathService;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DebugController extends AbstractController
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ImagePathService $imagePathService
    ) {
    }

    #[Route('/debug/tenant', name: 'api_debug_tenant', methods: ['GET'])]
    public function debugTenant(Request $request): JsonResponse
    {
        try {
            $host = $request->getHost();
            $routeTenant = $request->attributes->get('dominio');
            $currentTenant = $this->tenantManager->getCurrentTenant();
            $domainMappings = $this->tenantManager->getDomainMappings();
            $allowedTenants = $this->tenantManager->getAllowedTenants();
            
            $debugInfo = [
                'host' => $host,
                'route_tenant' => $routeTenant,
                'current_tenant' => $currentTenant,
                'domain_mappings' => $domainMappings,
                'allowed_tenants' => array_keys($allowedTenants),
                'is_rs_valid' => $this->tenantManager->isValidTenant('rs'),
                'request_uri' => $request->getRequestUri(),
                'request_method' => $request->getMethod(),
                'headers' => [
                    'host' => $request->headers->get('host'),
                    'x-forwarded-host' => $request->headers->get('x-forwarded-host'),
                    'x-forwarded-proto' => $request->headers->get('x-forwarded-proto'),
                ]
            ];

            return new JsonResponse([
                'success' => true,
                'debug_info' => $debugInfo
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    #[Route('/debug/images', name: 'api_debug_images', methods: ['GET'])]
    public function debugImages(string $dominio, Request $request): JsonResponse
    {
        try {
            $em = $this->tenantManager->getEntityManager();
            
            // Obtener algunos beneficios con imágenes
            $benefits = $em->createQuery(
                'SELECT b FROM App\Entity\App\Benefit b 
                 WHERE b.image IS NOT NULL 
                 AND b.status = :status 
                 ORDER BY b.id DESC'
            )
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->setMaxResults(3)
            ->getResult();

            $imageDebugInfo = [];
            $uploadsDir = '/var/www/html/public/uploads'; // Ruta típica en producción

            foreach ($benefits as $benefit) {
                $relativePath = $benefit->getImage();
                $fullPath = $uploadsDir . '/' . $relativePath;
                $exists = file_exists($fullPath);
                
                $imageInfo = [
                    'benefit_id' => $benefit->getId(),
                    'benefit_title' => $benefit->getTitle(),
                    'relative_path' => $relativePath,
                    'full_path' => $fullPath,
                    'file_exists' => $exists,
                ];

                if ($exists) {
                    $imageInfo['file_size'] = filesize($fullPath);
                    $imageInfo['mime_type'] = mime_content_type($fullPath);
                    $imageInfo['permissions'] = substr(sprintf('%o', fileperms($fullPath)), -4);
                }

                $imageDebugInfo[] = $imageInfo;
            }

            return new JsonResponse([
                'success' => true,
                'uploads_directory' => $uploadsDir,
                'images' => $imageDebugInfo,
                'total_benefits_with_images' => count($benefits)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    #[Route('/{dominio}/api/debug/image-urls', name: 'api_debug_image_urls', methods: ['GET'])]
    public function debugImageUrls(string $dominio, Request $request): JsonResponse
    {
        try {

            // Ejemplos de rutas relativas
            $testPaths = [
                'benefit/file_1759178110.png',
                'social_media/screenshotfrom20250910135037-68c1d879a74f8.png',
                'profile/user_photo.jpg',
                null
            ];

            $results = [];
            foreach ($testPaths as $path) {
                $fullUrl = $this->imagePathService->generateFullPath($path);
                $results[] = [
                    'relative_path' => $path,
                    'full_url' => $fullUrl,
                    'protocol' => $fullUrl ? parse_url($fullUrl, PHP_URL_SCHEME) : null,
                    'is_https' => $fullUrl ? (parse_url($fullUrl, PHP_URL_SCHEME) === 'https') : null
                ];
            }

            return new JsonResponse([
                'success' => true,
                'environment' => $_ENV['APP_ENV'] ?? 'unknown',
                'configured_dev_url' => $_ENV['APP_URL_DEV'] ?? 'not set',
                'configured_prod_url' => $_ENV['APP_URL_PROD'] ?? 'not set',
                'test_results' => $results
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
