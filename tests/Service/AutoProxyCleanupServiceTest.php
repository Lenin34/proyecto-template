<?php

namespace App\Tests\Service;

use App\Entity\App\User;
use App\Service\AutoProxyCleanupService;
use App\Service\EntityProxyCleanerService;
use App\Service\TenantManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AutoProxyCleanupServiceTest extends TestCase
{
    private AutoProxyCleanupService $service;
    private MockObject $tenantManager;
    private MockObject $proxyCleanerService;
    private MockObject $logger;
    private MockObject $entityManager;

    protected function setUp(): void
    {
        $this->tenantManager = $this->createMock(TenantManager::class);
        $this->proxyCleanerService = $this->createMock(EntityProxyCleanerService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new AutoProxyCleanupService(
            $this->tenantManager,
            $this->proxyCleanerService,
            $this->logger
        );
    }

    public function testSafeExecuteSucceedsOnFirstAttempt(): void
    {
        $expectedResult = 'success';
        
        $operation = function() use ($expectedResult) {
            return $expectedResult;
        };

        $result = $this->service->safeExecute($operation);

        $this->assertEquals($expectedResult, $result);
    }

    public function testSafeExecuteRetriesOnProxyError(): void
    {
        $attempt = 0;
        $expectedResult = 'success';
        
        $operation = function() use (&$attempt, $expectedResult) {
            $attempt++;
            if ($attempt === 1) {
                throw new \Exception('Unable to find "Proxies\\__CG__\\App\\Entity\\Company" entity identifier');
            }
            return $expectedResult;
        };

        $this->tenantManager
            ->expects($this->once())
            ->method('clearCurrentEntityManager');

        $this->logger
            ->expects($this->once())
            ->method('info');

        $result = $this->service->safeExecute($operation);

        $this->assertEquals($expectedResult, $result);
        $this->assertEquals(2, $attempt);
    }

    public function testAutoCleanUserSuccess(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->proxyCleanerService
            ->expects($this->once())
            ->method('cleanAllProxyReferences')
            ->with($user, $this->entityManager)
            ->willReturn($user);

        $result = $this->service->autoCleanUser($user, $this->entityManager);

        $this->assertSame($user, $result);
    }

    public function testSafeGetUserCompanyIdSuccess(): void
    {
        $user = new User();
        $expectedCompanyId = 123;

        $this->proxyCleanerService
            ->expects($this->once())
            ->method('cleanAllProxyReferences')
            ->with($user, $this->entityManager)
            ->willReturn($user);

        $this->proxyCleanerService
            ->expects($this->once())
            ->method('getCompanyIdSafely')
            ->with($user, $this->entityManager)
            ->willReturn($expectedCompanyId);

        $result = $this->service->safeGetUserCompanyId($user, $this->entityManager);

        $this->assertEquals($expectedCompanyId, $result);
    }

    public function testSafePersistAndFlushSuccess(): void
    {
        $user = new User();

        $this->proxyCleanerService
            ->expects($this->once())
            ->method('cleanAllProxyReferences')
            ->with($user, $this->entityManager)
            ->willReturn($user);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($user);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->safePersistAndFlush($this->entityManager, $user);
    }

    public function testCleanupEntityManager(): void
    {
        $this->tenantManager
            ->expects($this->once())
            ->method('clearCurrentEntityManager');

        $this->service->cleanupEntityManager();
    }
}
