<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Servicio de respaldo para cuando Twilio no esté disponible
 * Permite que la aplicación continúe funcionando sin SMS/WhatsApp
 */
class FallbackNotificationService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Simula el envío de SMS cuando Twilio no está disponible
     */
    public function logSMSFallback(string $to, string $message): bool
    {
        $this->logger->warning('SMS no enviado - Twilio no disponible', [
            'to' => $to,
            'message_preview' => substr($message, 0, 50) . '...',
            'fallback_action' => 'logged_only',
            'reason' => 'twilio_credentials_invalid'
        ]);

        // En el futuro, aquí podrías implementar un servicio alternativo
        // como SendGrid, Amazon SNS, etc.
        
        return false; // Retorna false porque no se envió realmente
    }

    /**
     * Simula el envío de WhatsApp cuando Twilio no está disponible
     */
    public function logWhatsAppFallback(string $to, string $templateName, array $templateParams): bool
    {
        $this->logger->warning('WhatsApp no enviado - Twilio no disponible', [
            'to' => $to,
            'template' => $templateName,
            'params' => $templateParams,
            'fallback_action' => 'logged_only',
            'reason' => 'twilio_credentials_invalid'
        ]);

        return false; // Retorna false porque no se envió realmente
    }

    /**
     * Registra que se necesita reenviar un mensaje cuando Twilio esté disponible
     */
    public function queueForRetry(string $type, array $data): void
    {
        $this->logger->info('Mensaje encolado para reenvío cuando Twilio esté disponible', [
            'type' => $type,
            'data' => $data,
            'queued_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        ]);

        // Aquí podrías guardar en base de datos para reenviar después
    }
}
