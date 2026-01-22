<?php

namespace App\Tests\Service;

use App\Service\TenantManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantManagerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private TenantManager $tenantManager;
    private Connection $connection;

    protected function setUp(): void
    {
        // Mock del EntityManager y Connection
        $this->connection = $this->createMock(Connection::class);
        $this->connection
            ->method('getParams')
            ->willReturn([
                'driver' => 'mysql',
                'user' => 'test_user',
                'password' => 'test_pass',
                'host' => 'localhost',
                'port' => 3306
            ]);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager
            ->method('getConnection')
            ->willReturn($this->connection);

        // Mock del RequestStack
        $this->requestStack = $this->createMock(RequestStack::class);
        
        // Crear instancia de TenantManager
        $this->tenantManager = new TenantManager($this->entityManager, $this->requestStack);
    }

    public function testIsValidTenant(): void
    {
        $this->assertTrue($this->tenantManager->isValidTenant('ts'));
        $this->assertTrue($this->tenantManager->isValidTenant('SNT'));
        $this->assertFalse($this->tenantManager->isValidTenant('invalid'));
    }

    public function testGetAllowedTenants(): void
    {
        $expectedTenants = ['ts', 'SNT'];
        $this->assertEquals($expectedTenants, $this->tenantManager->getAllowedTenants());
    }

    public function testSetCurrentTenantWithValidTenant(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('close');

        $this->tenantManager->setCurrentTenant('ts');
        $this->assertEquals('ts', $this->tenantManager->getCurrentTenant());
    }

    public function testSetCurrentTenantWithInvalidTenant(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->tenantManager->setCurrentTenant('invalid');
    }

    public function testGetCurrentTenantFromRequest(): void
    {
        $request = new Request();
        $request->attributes->set('dominio', 'ts');

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->assertEquals('ts', $this->tenantManager->getCurrentTenant());
    }

    public function testGetCurrentTenantWithNoRequest(): void
    {
        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No request found');
        $this->tenantManager->getCurrentTenant();
    }

    public function testGetTenantConnection(): void
    {
        $this->tenantManager->setCurrentTenant('ts');
        
        $expectedUrl = 'mysql://test_user:test_pass@localhost:3306/msc-app-ts';
        $connection = $this->tenantManager->getTenantConnection();
        
        $this->assertEquals($expectedUrl, $connection['url']);
    }

    public function testGetTenantConnectionWithoutCurrentTenant(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->tenantManager->getTenantConnection();
    }
} 
