<?php

namespace App\Service;

use App\Entity\Master\Tenant;
use App\Enum\Status;
use App\Service\ImageUploadService;
use App\Service\TenantManager;
use App\Service\ImagePathService;
use App\Service\ApplicationErrorService;
use App\Enum\ErrorCodes\TenantLogoErrorCodes;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;

class TenantLogoService
{
    private const LOGO_SUBDIRECTORY = 'tenant-logos';
    private const DEFAULT_LOGO_PATH = '/images/logo.png';
    
    // Mapeo legacy para compatibilidad durante transición
    private const LEGACY_LOGO_MAPPING = [
        'ts' => '/images/logos/logoTS.svg',
        'rs' => '/images/logos/logoRS.jpg',
        'issemym' => '/images/logo.png', // Fallback local si no hay en BD
    ];

    private ImageUploadService $imageUploadService;
    private TenantManager $tenantManager;
    private ImagePathService $imagePathService;
    private ApplicationErrorService $applicationErrorService;
    private LoggerInterface $logger;
    private string $uploadsDirectory;

    public function __construct(
        ImageUploadService $imageUploadService,
        TenantManager $tenantManager,
        ImagePathService $imagePathService,
        ApplicationErrorService $applicationErrorService,
        LoggerInterface $logger,
        string $uploadsDirectory
    ) {
        $this->imageUploadService = $imageUploadService;
        $this->tenantManager = $tenantManager;
        $this->imagePathService = $imagePathService;
        $this->applicationErrorService = $applicationErrorService;
        $this->logger = $logger;
        $this->uploadsDirectory = $uploadsDirectory;
    }

    /**
     * Sube un logo para un tenant específico
     */
    public function uploadTenantLogo(UploadedFile $file, string $tenantDomain): ?string
    {
        try {
            $this->logger->info('Iniciando subida de logo para tenant', [
                'tenant' => $tenantDomain,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize()
            ]);

            // Validar que el tenant existe
            if (!$this->tenantExists($tenantDomain)) {
                $this->applicationErrorService->createError(
                    TenantLogoErrorCodes::TENANT_LOGO_SERVICE_TENANT_NOT_FOUND,
                    ['tenant_domain' => $tenantDomain]
                );
                return null;
            }

            // Subir imagen usando el servicio existente
            $relativePath = $this->imageUploadService->uploadImage($file, self::LOGO_SUBDIRECTORY);
            
            if (!$relativePath) {
                $this->logger->error('Error al subir logo', [
                    'tenant' => $tenantDomain,
                    'file_name' => $file->getClientOriginalName()
                ]);
                return null;
            }

            // Eliminar logo anterior si existe
            $this->deleteOldLogo($tenantDomain);

            // Actualizar en base de datos
            $success = $this->updateTenantLogo($tenantDomain, $relativePath);
            
            if (!$success) {
                $this->logger->error('Error al actualizar logo en base de datos', [
                    'tenant' => $tenantDomain,
                    'relative_path' => $relativePath
                ]);
                return null;
            }

            $this->logger->info('Logo subido exitosamente', [
                'tenant' => $tenantDomain,
                'relative_path' => $relativePath
            ]);

            return $relativePath;

        } catch (\Exception $e) {
            $this->logger->error('Error inesperado al subir logo', [
                'tenant' => $tenantDomain,
                'error' => $e->getMessage()
            ]);
            
            $this->applicationErrorService->createError(
                TenantLogoErrorCodes::TENANT_LOGO_SERVICE_UPLOAD_FAILED,
                [
                    'tenant_domain' => $tenantDomain,
                    'error_message' => $e->getMessage()
                ]
            );
            
            return null;
        }
    }

    /**
     * Obtiene la ruta relativa del logo de un tenant
     */
    public function getTenantLogo(string $tenantDomain): ?string
    {
        try {
            // Timeout rápido para evitar bloqueos en la página de login
            $timeout = 2; // 2 segundos máximo
            $startTime = microtime(true);

            $em = $this->tenantManager->getEntityManager('Master');

            // Verificar timeout antes de la consulta
            if ((microtime(true) - $startTime) > $timeout) {
                $this->logger->warning('Timeout al conectar a Master para logo', ['tenant' => $tenantDomain]);
                return null;
            }

            $tenant = $em->getRepository(Tenant::class)->findOneBy([
                'dominio' => $tenantDomain,
                'status' => Status::ACTIVE
            ]);

            return $tenant ? $tenant->getLogo() : null;

        } catch (\Exception $e) {
            $this->logger->error('Error al obtener logo del tenant', [
                'tenant' => $tenantDomain,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Obtiene la URL completa del logo de un tenant con sistema de fallbacks
     * PRIORIDAD: 1) Base de datos (configuración Master), 2) Legacy, 3) Default
     */
    public function getLogoUrl(string $tenantDomain): string
    {
        try {
            // 1. PRIMERO: Intentar logo específico del tenant desde BD (FUENTE DE VERDAD)
            $logoPath = $this->getTenantLogo($tenantDomain);
            if ($logoPath && $this->logoFileExists($logoPath)) {
                $fullUrl = $this->imagePathService->generateFullPath($logoPath);
                if ($fullUrl) {
                    $this->logger->debug('Logo encontrado en BD (configuración Master)', [
                        'tenant' => $tenantDomain,
                        'path' => $logoPath,
                        'url' => $fullUrl
                    ]);
                    return $fullUrl;
                }
            }

            // 2. SEGUNDO: Intentar logo legacy (compatibilidad) - Solo si NO hay en BD
            $legacyLogo = $this->getLegacyLogo($tenantDomain);
            if ($legacyLogo && $this->staticLogoExists($legacyLogo)) {
                $this->logger->debug('Usando logo legacy (fallback)', [
                    'tenant' => $tenantDomain,
                    'legacy_path' => $legacyLogo
                ]);
                return $legacyLogo;
            }

            // 3. TERCERO: Fallback final a logo por defecto
            $this->logger->debug('Usando logo por defecto', [
                'tenant' => $tenantDomain,
                'default_path' => self::DEFAULT_LOGO_PATH
            ]);

            return self::DEFAULT_LOGO_PATH;

        } catch (\Exception $e) {
            $this->logger->error('Error al obtener URL del logo', [
                'tenant' => $tenantDomain,
                'error' => $e->getMessage()
            ]);

            return self::DEFAULT_LOGO_PATH;
        }
    }

    /**
     * Obtiene la URL del logo por defecto
     */
    public function getDefaultLogoUrl(): string
    {
        return self::DEFAULT_LOGO_PATH;
    }

    /**
     * Obtiene información completa del logo de un tenant
     */
    public function getTenantLogoInfo(string $tenantDomain): array
    {
        try {
            $logoPath = $this->getTenantLogo($tenantDomain);
            $logoUrl = $this->getLogoUrl($tenantDomain);
            $isDefault = $logoPath === null;
            $isLegacy = !$isDefault && $this->isLegacyLogo($tenantDomain, $logoUrl);

            return [
                'tenant_domain' => $tenantDomain,
                'logo_path' => $logoPath,
                'logo_url' => $logoUrl,
                'is_default' => $isDefault,
                'is_legacy' => $isLegacy,
                'exists' => $logoPath ? $this->logoFileExists($logoPath) : false,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error al obtener información del logo', [
                'tenant' => $tenantDomain,
                'error' => $e->getMessage()
            ]);

            return [
                'tenant_domain' => $tenantDomain,
                'logo_path' => null,
                'logo_url' => self::DEFAULT_LOGO_PATH,
                'is_default' => true,
                'is_legacy' => false,
                'exists' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verifica si un logo es legacy
     */
    private function isLegacyLogo(string $tenantDomain, string $logoUrl): bool
    {
        $legacyLogo = $this->getLegacyLogo($tenantDomain);
        return $legacyLogo && $logoUrl === $legacyLogo;
    }

    /**
     * Elimina el logo de un tenant
     */
    public function deleteTenantLogo(string $tenantDomain): bool
    {
        try {
            $logoPath = $this->getTenantLogo($tenantDomain);
            
            if ($logoPath) {
                // Eliminar archivo físico
                $this->deleteLogoFile($logoPath);
                
                // Actualizar BD
                return $this->updateTenantLogo($tenantDomain, null);
            }
            
            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error al eliminar logo', [
                'tenant' => $tenantDomain,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verifica si un tenant existe
     */
    private function tenantExists(string $tenantDomain): bool
    {
        try {
            $em = $this->tenantManager->getEntityManager('Master');
            
            $tenant = $em->getRepository(Tenant::class)->findOneBy([
                'dominio' => $tenantDomain,
                'status' => Status::ACTIVE
            ]);
            
            return $tenant !== null;

        } catch (\Exception $e) {
            $this->logger->error('Error al verificar existencia del tenant', [
                'tenant' => $tenantDomain,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Actualiza el campo logo en la base de datos
     */
    private function updateTenantLogo(string $tenantDomain, ?string $logoPath): bool
    {
        try {
            $em = $this->tenantManager->getEntityManager('Master');
            
            $tenant = $em->getRepository(Tenant::class)->findOneBy([
                'dominio' => $tenantDomain,
                'status' => Status::ACTIVE
            ]);
            
            if (!$tenant) {
                return false;
            }
            
            $tenant->setLogo($logoPath);
            $tenant->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            
            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error al actualizar logo en BD', [
                'tenant' => $tenantDomain,
                'logo_path' => $logoPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Elimina el logo anterior de un tenant
     */
    private function deleteOldLogo(string $tenantDomain): void
    {
        try {
            $oldLogoPath = $this->getTenantLogo($tenantDomain);
            if ($oldLogoPath) {
                $this->deleteLogoFile($oldLogoPath);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Error al eliminar logo anterior', [
                'tenant' => $tenantDomain,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Elimina un archivo de logo del filesystem
     */
    private function deleteLogoFile(string $relativePath): void
    {
        try {
            $fullPath = $this->getUploadsDirectory() . '/' . $relativePath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
                $this->logger->debug('Archivo de logo eliminado', ['path' => $fullPath]);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Error al eliminar archivo de logo', [
                'path' => $relativePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verifica si un archivo de logo existe en el filesystem
     */
    private function logoFileExists(string $relativePath): bool
    {
        try {
            $fullPath = $this->getUploadsDirectory() . '/' . $relativePath;
            return file_exists($fullPath);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verifica si un logo estático existe
     */
    private function staticLogoExists(string $staticPath): bool
    {
        try {
            $fullPath = $this->getPublicDirectory() . $staticPath;
            return file_exists($fullPath);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene el logo legacy para compatibilidad
     */
    private function getLegacyLogo(string $tenantDomain): ?string
    {
        return self::LEGACY_LOGO_MAPPING[$tenantDomain] ?? null;
    }

    /**
     * Obtiene el directorio de uploads
     */
    private function getUploadsDirectory(): string
    {
        return $this->uploadsDirectory;
    }

    /**
     * Obtiene el directorio público
     */
    private function getPublicDirectory(): string
    {
        return dirname($this->uploadsDirectory);
    }
}
