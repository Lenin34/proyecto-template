<?php

namespace App\Tests\Integration;

use App\Service\TenantLogoService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TenantLogoIntegrationTest extends KernelTestCase
{
    private TenantLogoService $tenantLogoService;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->tenantLogoService = static::getContainer()->get(TenantLogoService::class);
    }

    public function testGetDefaultLogoUrl(): void
    {
        $defaultUrl = $this->tenantLogoService->getDefaultLogoUrl();
        
        $this->assertEquals('/images/logos/logoDefault.svg', $defaultUrl);
    }

    public function testGetLogoUrlForNonExistentTenant(): void
    {
        $logoUrl = $this->tenantLogoService->getLogoUrl('nonexistent-tenant');
        
        // Debería retornar el logo por defecto
        $this->assertEquals('/images/logos/logoDefault.svg', $logoUrl);
    }

    public function testGetTenantLogoInfoForNonExistentTenant(): void
    {
        $logoInfo = $this->tenantLogoService->getTenantLogoInfo('nonexistent-tenant');
        
        $this->assertIsArray($logoInfo);
        $this->assertEquals('nonexistent-tenant', $logoInfo['tenant_domain']);
        $this->assertNull($logoInfo['logo_path']);
        $this->assertEquals('/images/logos/logoDefault.svg', $logoInfo['logo_url']);
        $this->assertTrue($logoInfo['is_default']);
        $this->assertFalse($logoInfo['is_legacy']);
        $this->assertFalse($logoInfo['exists']);
    }

    public function testGetLogoUrlForKnownTenant(): void
    {
        // Probar con tenant 'ts' que debería tener mapeo legacy
        $logoUrl = $this->tenantLogoService->getLogoUrl('ts');
        
        // Debería retornar el logo legacy o el por defecto
        $this->assertIsString($logoUrl);
        $this->assertNotEmpty($logoUrl);
    }

    public function testGetTenantLogoInfoForKnownTenant(): void
    {
        // Probar con tenant 'ts'
        $logoInfo = $this->tenantLogoService->getTenantLogoInfo('ts');
        
        $this->assertIsArray($logoInfo);
        $this->assertEquals('ts', $logoInfo['tenant_domain']);
        $this->assertIsString($logoInfo['logo_url']);
        $this->assertIsBool($logoInfo['is_default']);
        $this->assertIsBool($logoInfo['is_legacy']);
        $this->assertIsBool($logoInfo['exists']);
    }

    /**
     * Test que requiere un archivo real - solo para desarrollo
     * @group manual
     */
    public function testUploadTenantLogoWithRealFile(): void
    {
        $this->markTestSkipped('Test manual - requiere archivo real');
        
        // Crear un archivo de prueba
        $testImagePath = sys_get_temp_dir() . '/test_logo.png';
        
        // Crear una imagen PNG simple de 1x1 pixel
        $image = imagecreate(1, 1);
        imagecolorallocate($image, 255, 255, 255);
        imagepng($image, $testImagePath);
        imagedestroy($image);
        
        $uploadedFile = new UploadedFile(
            $testImagePath,
            'test_logo.png',
            'image/png',
            null,
            true
        );
        
        // Intentar subir para un tenant de prueba
        $result = $this->tenantLogoService->uploadTenantLogo($uploadedFile, 'test-tenant');
        
        // Limpiar archivo temporal
        if (file_exists($testImagePath)) {
            unlink($testImagePath);
        }
        
        // El resultado debería ser null porque 'test-tenant' no existe
        $this->assertNull($result);
    }

    public function testDeleteTenantLogoForNonExistentTenant(): void
    {
        $result = $this->tenantLogoService->deleteTenantLogo('nonexistent-tenant');
        
        // Debería retornar true porque no hay nada que eliminar
        $this->assertTrue($result);
    }

    /**
     * Test de rendimiento básico
     */
    public function testPerformanceOfLogoRetrieval(): void
    {
        $startTime = microtime(true);
        
        // Hacer múltiples llamadas
        for ($i = 0; $i < 10; $i++) {
            $this->tenantLogoService->getLogoUrl('ts');
            $this->tenantLogoService->getTenantLogoInfo('ts');
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Debería completarse en menos de 1 segundo
        $this->assertLessThan(1.0, $executionTime, 'Logo retrieval should be fast');
    }

    /**
     * Test de consistencia entre métodos
     */
    public function testConsistencyBetweenMethods(): void
    {
        $tenantDomain = 'ts';
        
        $logoUrl = $this->tenantLogoService->getLogoUrl($tenantDomain);
        $logoInfo = $this->tenantLogoService->getTenantLogoInfo($tenantDomain);
        
        // La URL del logo debería ser consistente
        $this->assertEquals($logoUrl, $logoInfo['logo_url']);
        
        // Si es default, no debería tener path
        if ($logoInfo['is_default']) {
            $this->assertNull($logoInfo['logo_path']);
        }
        
        // Si no es default, debería tener path
        if (!$logoInfo['is_default']) {
            $this->assertNotNull($logoInfo['logo_path']);
        }
    }
}
