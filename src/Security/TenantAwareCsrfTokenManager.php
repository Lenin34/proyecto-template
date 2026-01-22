<?php

namespace App\Security;

use App\Service\TenantManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

class TenantAwareCsrfTokenManager implements CsrfTokenManagerInterface
{
    private $storage;
    private $generator;
    private $tenantManager;
    private $requestStack;

    public function __construct(TenantManager $tenantManager, RequestStack $requestStack)
    {
        $this->generator = new UriSafeTokenGenerator();
        $this->storage = new SessionTokenStorage($requestStack);
        $this->tenantManager = $tenantManager;
        $this->requestStack = $requestStack;
    }

    /**
     * Get the effective tenant for CSRF operations.
     * Priority: Route dominio > TenantManager > null
     */
    private function getEffectiveTenant(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        // Priority 1: Route dominio (most reliable for multi-tenant URLs)
        if ($request) {
            $routeDominio = $request->attributes->get('dominio');
            if ($routeDominio && $this->tenantManager->isValidTenant($routeDominio)) {
                return $routeDominio;
            }
        }

        // Priority 2: TenantManager's current tenant
        return $this->tenantManager->getCurrentTenant();
    }

    public function getToken(string $tokenId): CsrfToken
    {
        $tenant = $this->getEffectiveTenant();
        $storageTokenId = $this->getTokenIdWithTenant($tokenId, $tenant);

        if ($this->storage->hasToken($storageTokenId)) {
            $value = $this->storage->getToken($storageTokenId);
        } else {
            $value = $this->generator->generateToken();
            $this->storage->setToken($storageTokenId, $value);
        }

        return new CsrfToken($tokenId, $value);
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        $tenant = $this->getEffectiveTenant();
        $storageTokenId = $this->getTokenIdWithTenant($tokenId, $tenant);

        $value = $this->generator->generateToken();
        $this->storage->setToken($storageTokenId, $value);

        return new CsrfToken($tokenId, $value);
    }

    public function removeToken(string $tokenId): ?string
    {
        $tenant = $this->getEffectiveTenant();
        return $this->storage->removeToken(
            $this->getTokenIdWithTenant($tokenId, $tenant)
        );
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        $tenant = $this->getEffectiveTenant();
        $tokenId = $this->getTokenIdWithTenant($token->getId(), $tenant);

        if (!$this->storage->hasToken($tokenId)) {
            return false;
        }

        return hash_equals(
            $this->storage->getToken($tokenId),
            $token->getValue()
        );
    }

    private function getTokenIdWithTenant(string $tokenId, ?string $tenant): string
    {
        if (!$tenant) {
            return $tokenId;
        }
        return sprintf('%s_%s', $tokenId, $tenant);
    }
}
