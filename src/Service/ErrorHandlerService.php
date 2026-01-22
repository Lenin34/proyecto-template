<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class ErrorHandlerService
{
    public const ERROR_INVALID_TENANT = 'INVALID_TENANT';
    public const ERROR_TENANT_MISMATCH = 'TENANT_MISMATCH';
    public const ERROR_RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    public const ERROR_INVALID_TOKEN = 'INVALID_TOKEN';

    private array $errorMessages = [
        self::ERROR_INVALID_TENANT => 'El tenant especificado no es válido',
        self::ERROR_TENANT_MISMATCH => 'El tenant no coincide con el token proporcionado',
        self::ERROR_RATE_LIMIT_EXCEEDED => 'Se ha excedido el límite de solicitudes',
        self::ERROR_INVALID_TOKEN => 'El token JWT no es válido o ha expirado'
    ];

    private array $errorHttpCodes = [
        self::ERROR_INVALID_TENANT => Response::HTTP_NOT_FOUND,
        self::ERROR_TENANT_MISMATCH => Response::HTTP_FORBIDDEN,
        self::ERROR_RATE_LIMIT_EXCEEDED => Response::HTTP_TOO_MANY_REQUESTS,
        self::ERROR_INVALID_TOKEN => Response::HTTP_UNAUTHORIZED
    ];

    private LoggerInterface $logger;
    private TenantManager $tenantManager;

    public function __construct(
        LoggerInterface $logger,
        TenantManager $tenantManager
    ) {
        $this->logger = $logger;
        $this->tenantManager = $tenantManager;
    }

    public function createErrorResponse(
        string $errorCode,
        ?string $customMessage = null,
        array $details = [],
        ?int $statusCode = null
    ): JsonResponse {
        $message = $customMessage ?? $this->errorMessages[$errorCode] ?? 'Error desconocido';
        $status = $statusCode ?? $this->errorHttpCodes[$errorCode] ?? Response::HTTP_INTERNAL_SERVER_ERROR;

        $tenant = null;
        try {
            $tenant = $this->tenantManager->getCurrentTenant();
        } catch (\Exception $e) {
            // Si no podemos obtener el tenant, continuamos sin él
        }

        $errorData = [
            'code' => $errorCode,
            'message' => $message,
            'details' => $details
        ];

        // Log del error
        $this->logger->error('Error en la aplicación', [
            'error_code' => $errorCode,
            'message' => $message,
            'details' => $details,
            'tenant' => $tenant,
            'status_code' => $status
        ]);

        return new JsonResponse($errorData, $status);
    }

    public function handleTenantError(\Exception $e, ?string $tenant = null): JsonResponse
    {
        if ($e instanceof \RuntimeException && str_contains($e->getMessage(), 'Invalid tenant')) {
            return $this->createErrorResponse(
                self::ERROR_INVALID_TENANT,
                null,
                ['tenant' => $tenant]
            );
        }

        return $this->createErrorResponse(
            'INTERNAL_ERROR',
            'Error interno del servidor',
            [],
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    public function handleRateLimitError(string $tenant, string $identifier): JsonResponse
    {
        return $this->createErrorResponse(
            self::ERROR_RATE_LIMIT_EXCEEDED,
            null,
            [
                'tenant' => $tenant,
                'identifier' => $identifier,
                'retry_after' => 3600 // 1 hora por defecto
            ]
        );
    }

    public function handleTokenError(\Exception $e): JsonResponse
    {
        return $this->createErrorResponse(
            self::ERROR_INVALID_TOKEN,
            null,
            ['error' => $e->getMessage()]
        );
    }

    public function handleTenantMismatch(string $requestTenant, string $tokenTenant): JsonResponse
    {
        return $this->createErrorResponse(
            self::ERROR_TENANT_MISMATCH,
            null,
            [
                'request_tenant' => $requestTenant,
                'token_tenant' => $tokenTenant
            ]
        );
    }
} 