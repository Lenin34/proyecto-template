<?php

namespace App\Tests\Twig;

use App\Twig\TenantLogoExtension;
use App\Service\TenantLogoService;
use App\Service\TenantManager;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Twig\TwigFunction;

class TenantLogoExtensionTest extends TestCase
{
    private TenantLogoExtension $extension;
    private MockObject|TenantLogoService $tenantLogoService;
    private MockObject|TenantManager $tenantManager;
    private MockObject|LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->tenantLogoService = $this->createMock(TenantLogoService::class);
        $this->tenantManager = $this->createMock(TenantManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->extension = new TenantLogoExtension(
            $this->tenantLogoService,
            $this->tenantManager,
            $this->logger
        );
    }

    public function testGetFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertIsArray($functions);
        $this->assertCount(4, $functions);

        $functionNames = array_map(fn(TwigFunction $func) => $func->getName(), $functions);
        
        $this->assertContains('tenant_logo_url', $functionNames);
        $this->assertContains('current_tenant_logo', $functionNames);
        $this->assertContains('tenant_logo_info', $functionNames);
        $this->assertContains('tenant_has_custom_logo', $functionNames);
    }

    public function testGetTenantLogoUrlWithSpecificTenant(): void
    {
        $tenantDomain = 'test-tenant';
        $expectedUrl = 'https://example.com/uploads/tenant-logos/test.jpg';

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getLogoUrl')
            ->with($tenantDomain)
            ->willReturn($expectedUrl);

        $result = $this->extension->getTenantLogoUrl($tenantDomain);

        $this->assertEquals($expectedUrl, $result);
    }

    public function testGetTenantLogoUrlWithCurrentTenant(): void
    {
        $currentTenant = 'current-tenant';
        $expectedUrl = 'https://example.com/uploads/tenant-logos/current.jpg';

        $this->tenantManager
            ->expects($this->once())
            ->method('getCurrentTenant')
            ->willReturn($currentTenant);

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getLogoUrl')
            ->with($currentTenant)
            ->willReturn($expectedUrl);

        $result = $this->extension->getTenantLogoUrl();

        $this->assertEquals($expectedUrl, $result);
    }

    public function testGetTenantLogoUrlWithNoCurrentTenant(): void
    {
        $defaultUrl = '/images/logos/logoDefault.svg';

        $this->tenantManager
            ->expects($this->once())
            ->method('getCurrentTenant')
            ->willReturn(null);

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getDefaultLogoUrl')
            ->willReturn($defaultUrl);

        $result = $this->extension->getTenantLogoUrl();

        $this->assertEquals($defaultUrl, $result);
    }

    public function testGetTenantLogoUrlWithException(): void
    {
        $tenantDomain = 'error-tenant';
        $defaultUrl = '/images/logos/logoDefault.svg';

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getLogoUrl')
            ->with($tenantDomain)
            ->willThrowException(new \Exception('Test error'));

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getDefaultLogoUrl')
            ->willReturn($defaultUrl);

        $this->logger
            ->expects($this->once())
            ->method('error');

        $result = $this->extension->getTenantLogoUrl($tenantDomain);

        $this->assertEquals($defaultUrl, $result);
    }

    public function testGetCurrentTenantLogo(): void
    {
        $currentTenant = 'current-tenant';
        $expectedUrl = 'https://example.com/uploads/tenant-logos/current.jpg';

        $this->tenantManager
            ->expects($this->once())
            ->method('getCurrentTenant')
            ->willReturn($currentTenant);

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getLogoUrl')
            ->with($currentTenant)
            ->willReturn($expectedUrl);

        $result = $this->extension->getCurrentTenantLogo();

        $this->assertEquals($expectedUrl, $result);
    }

    public function testGetTenantLogoInfo(): void
    {
        $tenantDomain = 'test-tenant';
        $expectedInfo = [
            'tenant_domain' => $tenantDomain,
            'logo_path' => 'tenant-logos/test.jpg',
            'logo_url' => 'https://example.com/uploads/tenant-logos/test.jpg',
            'is_default' => false,
            'is_legacy' => false,
            'exists' => true,
        ];

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getTenantLogoInfo')
            ->with($tenantDomain)
            ->willReturn($expectedInfo);

        $result = $this->extension->getTenantLogoInfo($tenantDomain);

        $this->assertEquals($expectedInfo, $result);
    }

    public function testGetTenantLogoInfoWithCurrentTenant(): void
    {
        $currentTenant = 'current-tenant';
        $expectedInfo = [
            'tenant_domain' => $currentTenant,
            'logo_path' => null,
            'logo_url' => '/images/logos/logoDefault.svg',
            'is_default' => true,
            'is_legacy' => false,
            'exists' => false,
        ];

        $this->tenantManager
            ->expects($this->once())
            ->method('getCurrentTenant')
            ->willReturn($currentTenant);

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getTenantLogoInfo')
            ->with($currentTenant)
            ->willReturn($expectedInfo);

        $result = $this->extension->getTenantLogoInfo();

        $this->assertEquals($expectedInfo, $result);
    }

    public function testGetTenantLogoInfoWithNoCurrentTenant(): void
    {
        $defaultUrl = '/images/logos/logoDefault.svg';

        $this->tenantManager
            ->expects($this->once())
            ->method('getCurrentTenant')
            ->willReturn(null);

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getDefaultLogoUrl')
            ->willReturn($defaultUrl);

        $result = $this->extension->getTenantLogoInfo();

        $this->assertIsArray($result);
        $this->assertNull($result['tenant_domain']);
        $this->assertNull($result['logo_path']);
        $this->assertEquals($defaultUrl, $result['logo_url']);
        $this->assertTrue($result['is_default']);
        $this->assertFalse($result['is_legacy']);
        $this->assertFalse($result['exists']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testTenantHasCustomLogoTrue(): void
    {
        $tenantDomain = 'test-tenant';
        $logoInfo = [
            'is_default' => false,
            'is_legacy' => false,
            'exists' => true,
        ];

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getTenantLogoInfo')
            ->with($tenantDomain)
            ->willReturn($logoInfo);

        $result = $this->extension->tenantHasCustomLogo($tenantDomain);

        $this->assertTrue($result);
    }

    public function testTenantHasCustomLogoFalseDefault(): void
    {
        $tenantDomain = 'test-tenant';
        $logoInfo = [
            'is_default' => true,
            'is_legacy' => false,
            'exists' => false,
        ];

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getTenantLogoInfo')
            ->with($tenantDomain)
            ->willReturn($logoInfo);

        $result = $this->extension->tenantHasCustomLogo($tenantDomain);

        $this->assertFalse($result);
    }

    public function testTenantHasCustomLogoFalseLegacy(): void
    {
        $tenantDomain = 'test-tenant';
        $logoInfo = [
            'is_default' => false,
            'is_legacy' => true,
            'exists' => true,
        ];

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getTenantLogoInfo')
            ->with($tenantDomain)
            ->willReturn($logoInfo);

        $result = $this->extension->tenantHasCustomLogo($tenantDomain);

        $this->assertFalse($result);
    }

    public function testTenantHasCustomLogoFalseNotExists(): void
    {
        $tenantDomain = 'test-tenant';
        $logoInfo = [
            'is_default' => false,
            'is_legacy' => false,
            'exists' => false,
        ];

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getTenantLogoInfo')
            ->with($tenantDomain)
            ->willReturn($logoInfo);

        $result = $this->extension->tenantHasCustomLogo($tenantDomain);

        $this->assertFalse($result);
    }

    public function testTenantHasCustomLogoWithException(): void
    {
        $tenantDomain = 'error-tenant';

        $this->tenantLogoService
            ->expects($this->once())
            ->method('getTenantLogoInfo')
            ->with($tenantDomain)
            ->willThrowException(new \Exception('Test error'));

        $this->logger
            ->expects($this->once())
            ->method('error');

        $result = $this->extension->tenantHasCustomLogo($tenantDomain);

        $this->assertFalse($result);
    }
}
