<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;

class ErrorResponseService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function createErrorResponse(array $errorCode, array $details = []): JsonResponse
    {
        $this->logger->error('HTTP Error Response', [
            'code' => $errorCode['code'],
            'message' => $errorCode['message'],
            'details' => $details,
        ]);

        return new JsonResponse([
            'success' => false,
            'message' => $errorCode['message'] ?? 'Unknown error message',
            'error_code' => $errorCode['code'] ?? 'UNKNOWN_CODE',
            'status' => $errorCode['http_code'] ?? 500,
            'details' => $details,
        ], $errorCode['http_code'] ?? 500);
    }
}