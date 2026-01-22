<?php

namespace App\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp que identifica el tenant asociado a un mensaje.
 * Permite enrutar mensajes al transporte correcto en arquitectura multi-tenant.
 */
class TenantStamp implements StampInterface
{
    private string $tenantName;

    public function __construct(string $tenantName)
    {
        $this->tenantName = $tenantName;
    }

    public function getTenantName(): string
    {
        return $this->tenantName;
    }
}

