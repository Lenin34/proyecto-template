<?php

namespace App\Twig;

use App\Service\TenantLogoService;
use App\Service\TenantManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Psr\Log\LoggerInterface;

class TenantLogoExtension extends AbstractExtension
{
    private TenantLogoService $tenantLogoService;
    private TenantManager $tenantManager;
    private LoggerInterface $logger;

    public function __construct(
        TenantLogoService $tenantLogoService,
        TenantManager $tenantManager,
        LoggerInterface $logger
    ) {
        $this->tenantLogoService = $tenantLogoService;
        $this->tenantManager = $tenantManager;
        $this->logger = $logger;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tenant_logo_url', [$this, 'getTenantLogoUrl']),
            new TwigFunction('current_tenant_logo', [$this, 'getCurrentTenantLogo']),
            new TwigFunction('tenant_logo_info', [$this, 'getTenantLogoInfo']),
            new TwigFunction('tenant_has_custom_logo', [$this, 'tenantHasCustomLogo']),
        ];
    }

    /**
     * Obtiene la URL del logo para un tenant específico
     * 
     * @param string|null $tenantDomain Dominio del tenant (null = tenant actual)
     * @return string URL del logo
     */
    public function getTenantLogoUrl(?string $tenantDomain = null): string
    {
        try {
            $domain = $tenantDomain ?? $this->getCurrentTenantDomain();
            
            if (!$domain) {
                $this->logger->debug('No se pudo determinar el tenant, usando logo por defecto');
                return $this->tenantLogoService->getDefaultLogoUrl();
            }
            
            return $this->tenantLogoService->getLogoUrl($domain);

        } catch (\Exception $e) {
            $this->logger->error('Error en tenant_logo_url', [
                'tenant' => $tenantDomain,
                'error' => $e->getMessage()
            ]);
            
            return $this->tenantLogoService->getDefaultLogoUrl();
        }
    }

    /**
     * Obtiene la URL del logo del tenant actual
     * 
     * @return string URL del logo del tenant actual
     */
    public function getCurrentTenantLogo(): string
    {
        return $this->getTenantLogoUrl();
    }

    /**
     * Obtiene información completa del logo de un tenant
     * 
     * @param string|null $tenantDomain Dominio del tenant (null = tenant actual)
     * @return array Información del logo
     */
    public function getTenantLogoInfo(?string $tenantDomain = null): array
    {
        try {
            $domain = $tenantDomain ?? $this->getCurrentTenantDomain();
            
            if (!$domain) {
                return [
                    'tenant_domain' => null,
                    'logo_path' => null,
                    'logo_url' => $this->tenantLogoService->getDefaultLogoUrl(),
                    'is_default' => true,
                    'is_legacy' => false,
                    'exists' => false,
                    'error' => 'No se pudo determinar el tenant'
                ];
            }
            
            return $this->tenantLogoService->getTenantLogoInfo($domain);

        } catch (\Exception $e) {
            $this->logger->error('Error en tenant_logo_info', [
                'tenant' => $tenantDomain,
                'error' => $e->getMessage()
            ]);
            
            return [
                'tenant_domain' => $tenantDomain,
                'logo_path' => null,
                'logo_url' => $this->tenantLogoService->getDefaultLogoUrl(),
                'is_default' => true,
                'is_legacy' => false,
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verifica si un tenant tiene un logo personalizado (no default ni legacy)
     * 
     * @param string|null $tenantDomain Dominio del tenant (null = tenant actual)
     * @return bool True si tiene logo personalizado
     */
    public function tenantHasCustomLogo(?string $tenantDomain = null): bool
    {
        try {
            $logoInfo = $this->getTenantLogoInfo($tenantDomain);
            
            return !$logoInfo['is_default'] && !$logoInfo['is_legacy'] && $logoInfo['exists'];

        } catch (\Exception $e) {
            $this->logger->error('Error en tenant_has_custom_logo', [
                'tenant' => $tenantDomain,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Obtiene el dominio del tenant actual
     * 
     * @return string|null Dominio del tenant actual o null si no se puede determinar
     */
    private function getCurrentTenantDomain(): ?string
    {
        try {
            return $this->tenantManager->getCurrentTenant();

        } catch (\Exception $e) {
            $this->logger->warning('Error al obtener tenant actual', [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
}
