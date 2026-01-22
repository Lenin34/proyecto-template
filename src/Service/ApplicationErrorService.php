<?php
namespace App\Service;

use Psr\Log\LoggerInterface;

class ApplicationErrorService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function createError(array $errorCode, array $details = []): array
    {
        $this->logger->error('Application Error', [
            'code' => $errorCode['code'],
            'message' => $errorCode['message'],
            'details' => $details,
        ]);

        return [
            'error' => [
                'code' => $errorCode['code'],
                'message' => $errorCode['message'],
                'details' => $details,
            ],
        ];
    }
}