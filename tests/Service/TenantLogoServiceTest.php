<?php

namespace App\Tests\Service;

use App\Service\TenantLogoService;
use App\Service\ImageUploadService;
use App\Service\TenantManager;
use App\Service\ImagePathService;
use App\Service\ApplicationErrorService;
use App\Entity\Master\Tenant;
use App\Enum\Status;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TenantLogoServiceTest extends TestCase
{
    private TenantLogoService $tenantLogoService;
    private MockObject|ImageUploadService $imageUploadService;
    private MockObject|TenantManager $tenantManager;
    private MockObject|ImagePathService $imagePathService;
    private MockObject|ApplicationErrorService $applicationErrorService;
    private MockObject|LoggerInterface $logger;
    private string $uploadsDirectory;

    protected function setUp(): void
    {
        $this->imageUploadService = $this->createMock(ImageUploadService::class);
        $this->tenantManager = $this->createMock(TenantManager::class);
        $this->imagePathService = $this->createMock(ImagePathService::class);
        $this->applicationErrorService = $this->createMock(ApplicationErrorService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->uploadsDirectory = '/tmp/test-uploads';

        $this->tenantLogoService = new TenantLogoService(
            $this->imageUploadService,
            $this->tenantManager,
            $this->imagePathService,
            $this->applicationErrorService,
            $this->logger,
            $this->uploadsDirectory
        );
    }

    public function testGetDefaultLogoUrl(): void
    {
        $defaultUrl = $this->tenantLogoService->getDefaultLogoUrl();
        
        $this->assertEquals('/images/logos/logoDefault.svg', $defaultUrl);
    }

    public function testGetTenantLogoReturnsNullWhenTenantNotFound(): void
    {
        $tenantDomain = 'nonexistent';
        
        // Mock EntityManager y Repository
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $this->tenantManager
            ->expects($this->once())
            ->method('setCurrentTenant')
            ->with('Master');
            
        $this->tenantManager
            ->expects($this->once())
            ->method('getEntityManager')
            ->willReturn($entityManager);
            
        $entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Tenant::class)
            ->willReturn($repository);
            
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'dominio' => $tenantDomain,
                'status' => Status::ACTIVE
            ])
            ->willReturn(null);

        $result = $this->tenantLogoService->getTenantLogo($tenantDomain);
        
        $this->assertNull($result);
    }

    public function testGetTenantLogoReturnsLogoPathWhenTenantExists(): void
    {
        $tenantDomain = 'test-tenant';
        $logoPath = 'tenant-logos/test_logo.jpg';
        
        // Mock Tenant entity
        $tenant = $this->createMock(Tenant::class);
        $tenant
            ->expects($this->once())
            ->method('getLogo')
            ->willReturn($logoPath);
        
        // Mock EntityManager y Repository
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $this->tenantManager
            ->expects($this->once())
            ->method('setCurrentTenant')
            ->with('Master');
            
        $this->tenantManager
            ->expects($this->once())
            ->method('getEntityManager')
            ->willReturn($entityManager);
            
        $entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Tenant::class)
            ->willReturn($repository);
            
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'dominio' => $tenantDomain,
                'status' => Status::ACTIVE
            ])
            ->willReturn($tenant);

        $result = $this->tenantLogoService->getTenantLogo($tenantDomain);
        
        $this->assertEquals($logoPath, $result);
    }

    public function testGetLogoUrlReturnsFullUrlWhenLogoExists(): void
    {
        $tenantDomain = 'test-tenant';
        $logoPath = 'tenant-logos/test_logo.jpg';
        $fullUrl = 'https://example.com/uploads/tenant-logos/test_logo.jpg';
        
        // Mock Tenant entity
        $tenant = $this->createMock(Tenant::class);
        $tenant
            ->expects($this->once())
            ->method('getLogo')
            ->willReturn($logoPath);
        
        // Mock EntityManager y Repository
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $this->tenantManager
            ->expects($this->once())
            ->method('setCurrentTenant')
            ->with('Master');
            
        $this->tenantManager
            ->expects($this->once())
            ->method('getEntityManager')
            ->willReturn($entityManager);
            
        $entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Tenant::class)
            ->willReturn($repository);
            
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'dominio' => $tenantDomain,
                'status' => Status::ACTIVE
            ])
            ->willReturn($tenant);

        $this->imagePathService
            ->expects($this->once())
            ->method('generateFullPath')
            ->with($logoPath)
            ->willReturn($fullUrl);

        $result = $this->tenantLogoService->getLogoUrl($tenantDomain);
        
        $this->assertEquals($fullUrl, $result);
    }

    public function testGetLogoUrlReturnsDefaultWhenNoLogoExists(): void
    {
        $tenantDomain = 'test-tenant';
        
        // Mock EntityManager y Repository
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $this->tenantManager
            ->expects($this->once())
            ->method('setCurrentTenant')
            ->with('Master');
            
        $this->tenantManager
            ->expects($this->once())
            ->method('getEntityManager')
            ->willReturn($entityManager);
            
        $entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Tenant::class)
            ->willReturn($repository);
            
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'dominio' => $tenantDomain,
                'status' => Status::ACTIVE
            ])
            ->willReturn(null);

        $result = $this->tenantLogoService->getLogoUrl($tenantDomain);
        
        $this->assertEquals('/images/logos/logoDefault.svg', $result);
    }

    public function testUploadTenantLogoReturnsNullWhenTenantNotExists(): void
    {
        $tenantDomain = 'nonexistent';
        $uploadedFile = $this->createMock(UploadedFile::class);
        
        // Mock EntityManager y Repository para verificar existencia
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $this->tenantManager
            ->expects($this->once())
            ->method('setCurrentTenant')
            ->with('Master');
            
        $this->tenantManager
            ->expects($this->once())
            ->method('getEntityManager')
            ->willReturn($entityManager);
            
        $entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Tenant::class)
            ->willReturn($repository);
            
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'dominio' => $tenantDomain,
                'status' => Status::ACTIVE
            ])
            ->willReturn(null);

        $this->applicationErrorService
            ->expects($this->once())
            ->method('createError');

        $result = $this->tenantLogoService->uploadTenantLogo($uploadedFile, $tenantDomain);
        
        $this->assertNull($result);
    }

    public function testUploadTenantLogoSuccessful(): void
    {
        $tenantDomain = 'test-tenant';
        $logoPath = 'tenant-logos/test_logo.jpg';
        $uploadedFile = $this->createMock(UploadedFile::class);
        
        // Mock Tenant entity para verificar existencia
        $tenant = $this->createMock(Tenant::class);
        
        // Mock EntityManager y Repository para verificar existencia
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $this->tenantManager
            ->expects($this->exactly(2))
            ->method('setCurrentTenant')
            ->with('Master');
            
        $this->tenantManager
            ->expects($this->exactly(2))
            ->method('getEntityManager')
            ->willReturn($entityManager);
            
        $entityManager
            ->expects($this->exactly(2))
            ->method('getRepository')
            ->with(Tenant::class)
            ->willReturn($repository);
            
        // Primera llamada para verificar existencia
        $repository
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->with([
                'dominio' => $tenantDomain,
                'status' => Status::ACTIVE
            ])
            ->willReturn($tenant);

        // Mock ImageUploadService
        $this->imageUploadService
            ->expects($this->once())
            ->method('uploadImage')
            ->with($uploadedFile, 'tenant-logos')
            ->willReturn($logoPath);

        // Mock actualización en BD
        $tenant
            ->expects($this->once())
            ->method('setLogo')
            ->with($logoPath);
            
        $tenant
            ->expects($this->once())
            ->method('setUpdatedAt');

        $entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->tenantLogoService->uploadTenantLogo($uploadedFile, $tenantDomain);
        
        $this->assertEquals($logoPath, $result);
    }
}
