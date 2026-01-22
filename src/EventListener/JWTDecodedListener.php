<?php

namespace App\EventListener;

use App\Service\TenantManager;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class JWTDecodedListener
{
    private TenantManager $tenantManager;
    private RequestStack $requestStack;

    public function __construct(
        TenantManager $tenantManager,
        RequestStack $requestStack
    ) {
        $this->tenantManager = $tenantManager;
        $this->requestStack = $requestStack;
    }

    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            $event->markAsInvalid();
            return;
        }

        // Verificar que el token contiene información del tenant
        if (!isset($payload['tenant'])) {
            $event->markAsInvalid();
            return;
        }

        // Obtener el tenant de la URL actual
        $currentTenant = $request->attributes->get('dominio');

        // Verificar que el tenant del token es válido
        if (!$this->tenantManager->isValidTenant($payload['tenant'])) {
            $event->markAsInvalid();
            return;
        }

        // Verificar que el tenant del token coincide con el de la URL
        if ($currentTenant !== $payload['tenant']) {
            $event->markAsInvalid();
            return;
        }
    }
} 