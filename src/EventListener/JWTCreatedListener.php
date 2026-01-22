<?php

namespace App\EventListener;

use App\Service\TenantManager;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTCreatedListener
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

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $payload = $event->getData();
        $user = $event->getUser();
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            throw new \RuntimeException('No se encontró la solicitud actual');
        }
        
        // Obtener el tenant de la URL
        $tenant = $request->attributes->get('dominio');
        
        if (!$this->tenantManager->isValidTenant($tenant)) {
            throw new \RuntimeException('Tenant inválido');
        }

        // Añadir el tenant al payload del token
        $payload['tenant'] = $tenant;
        
        // Añadir información adicional del usuario si es necesario
        if ($user instanceof UserInterface) {
            $payload['username'] = $user->getUserIdentifier();
            $payload['roles'] = $user->getRoles();
        }

        $event->setData($payload);
    }
} 