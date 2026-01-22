<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class EmailVerificationService
{
    private MailerInterface $mailer;
    private string $fromAddress;

    public function __construct(MailerInterface $mailer, string $fromAddress)
    {
        $this->mailer = $mailer;
        $this->fromAddress = $fromAddress;
    }

    public function sendVerificationCode(string $recipientEmail, string $verificationCode, string $tenant): array
    {
        try {
            $templatePath = 'email/' . $tenant . '/verification_code.html.twig';
            $email = (new TemplatedEmail())
                ->from($this->fromAddress)
                ->to($recipientEmail)
                ->subject('Código de verificación')
                ->htmlTemplate($templatePath)
                ->context([
                    'verification_code' => $verificationCode,
                ]);

            $this->mailer->send($email);

            return ['success' => true];
        } catch (\Throwable $e) {
            error_log('Error sending email: ' . $e->getMessage());
            error_log('ERROR ENVIANDO EMAIL: ' . $e->getMessage());
            error_log('FROM: ' . $this->fromAddress);
            error_log('TO: ' . $recipientEmail);
            error_log('SMTP: ' . ($_ENV['MAILER_DSN'] ?? 'No configurado'));

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
}
