<?php

namespace App\Messenger\Middleware;

use App\Messenger\Stamp\TenantStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Middleware que enruta mensajes al transporte correcto según el tenant.
 * 
 * En arquitectura multi-tenant, cada tenant tiene su propia tabla messenger_messages.
 * Este middleware lee el TenantStamp y determina el transporte apropiado.
 */
class TenantAwareRoutingMiddleware implements MiddlewareInterface
{
    /**
     * Mapeo de tenants a nombres de transporte
     */
    private const TENANT_TRANSPORT_MAP = [
        'ts' => 'async_ts',
        'rs' => 'async_rs',
        'SNT' => 'async_snt',
        'issemym' => 'async_issemym',
    ];

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // Solo aplicar routing si el mensaje NO ha sido recibido aún (evitar loop)
        if ($envelope->last(ReceivedStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Solo aplicar routing si el mensaje NO ha sido enviado aún
        if ($envelope->last(SentStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Buscar el TenantStamp
        $tenantStamp = $envelope->last(TenantStamp::class);
        
        if ($tenantStamp) {
            $tenantName = $tenantStamp->getTenantName();
            
            // Determinar el transporte correcto
            $transportName = self::TENANT_TRANSPORT_MAP[$tenantName] ?? 'async';
            
            // Agregar TransportNamesStamp para forzar el uso del transporte específico
            $envelope = $envelope->with(new TransportNamesStamp([$transportName]));
            
            error_log(sprintf(
                '[TenantAwareRoutingMiddleware] Routing message to transport "%s" for tenant "%s"',
                $transportName,
                $tenantName
            ));
        } else {
            error_log('[TenantAwareRoutingMiddleware] No TenantStamp found, using default routing');
        }

        return $stack->next()->handle($envelope, $stack);
    }
}

