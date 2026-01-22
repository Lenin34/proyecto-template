<?php

namespace App\Security\RateLimiter;

use App\Service\TenantManager;
use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Bundle\SecurityBundle\Security;

class TenantRateLimiter extends AbstractRequestRateLimiter
{
    private RateLimiterFactory $factory;
    private TenantManager $tenantManager;
    private Security $security;

    public function __construct(
        RateLimiterFactory $factory,
        TenantManager $tenantManager,
        Security $security
    ) {
        $this->factory = $factory;
        $this->tenantManager = $tenantManager;
        $this->security = $security;
    }

    protected function getLimiters(Request $request): array
    {
        $tenant = $this->tenantManager->getCurrentTenant();

        if (!$tenant) {
            throw new \RuntimeException('No se encontro el Tenant.');
        }

        $user = $this->security->getUser();
        $userId = $user ? $user->getId() : 'anonymous';

        return [
            $this->factory->create($tenant.'_'.$request->getClientIp()),
            $this->factory->create($tenant.'_'.$userId)
        ];
    }
} 
