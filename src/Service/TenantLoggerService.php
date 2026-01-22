<?php

namespace App\Service;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class TenantLoggerService implements LoggerInterface
{
    private array $loggers = [];
    private RequestStack $requestStack;
    private string $logDir;
    private ?string $currentTenant = null;

    public function __construct(
        RequestStack $requestStack,
        string $logDir
    ) {
        $this->requestStack = $requestStack;
        $this->logDir = $logDir;
    }

    private function getLogger(): Logger
    {
        $tenant = $this->getCurrentTenantSafely();

        // Si ya tenemos un logger para este tenant, lo devolvemos
        if (isset($this->loggers[$tenant])) {
            return $this->loggers[$tenant];
        }

        // Crear nuevo logger para este tenant
        $this->loggers[$tenant] = $this->createLoggerForTenant($tenant);

        return $this->loggers[$tenant];
    }

    private function getCurrentTenantSafely(): string
    {
        try {
            // Si ya tenemos un tenant establecido manualmente, usarlo
            if ($this->currentTenant) {
                return $this->currentTenant;
            }

            // Obtener tenant desde la request actual
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $tenant = $request->attributes->get('dominio');
                if ($tenant) {
                    return $tenant;
                }

                // Fallback a sesión si está disponible
                if ($request->hasSession()) {
                    $session = $request->getSession();
                    $sessionTenant = $session->get('_tenant');
                    if ($sessionTenant) {
                        return $sessionTenant;
                    }
                }
            }

            // Fallback final
            return 'rs';
        } catch (\Throwable $e) {
            // En caso de error, usar tenant por defecto
            return 'rs';
        }
    }

    private function createLoggerForTenant(string $tenant): Logger
    {
        // Ensure the base log directory exists and is writable
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }

        $logger = new Logger($tenant);
        $logFilePath = sprintf('%s/%s.log', rtrim($this->logDir, '/'), $tenant);

        $handler = new RotatingFileHandler(
            $logFilePath,
            10,
            Logger::INFO
        );

        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            "Y-m-d H:i:s"
        );

        $handler->setFormatter($formatter);
        $logger->pushHandler($handler);

        // Add minimal context processor
        $logger->pushProcessor(function (LogRecord $record) use ($tenant): LogRecord {
            if (php_sapi_name() === 'cli') {
                return $record;
            }

            $record->extra = array_merge($record->extra ?? [], [
                'tenant' => $tenant
            ]);

            return $record;
        });

        return $logger;
    }

    public function emergency($message, array $context = []): void
    {
        $this->getLogger()->emergency($message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->getLogger()->alert($message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->getLogger()->critical($message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->getLogger()->error($message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->getLogger()->warning($message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->getLogger()->notice($message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->getLogger()->info($message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->getLogger()->debug($message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->getLogger()->log($level, $message, $context);
    }

    /**
     * Limpiar caché de loggers (útil para testing o cambios de tenant)
     */
    public function clearLoggerCache(): void
    {
        $this->loggers = [];
    }

    /**
     * Obtener información de debug sobre loggers activos
     */
    public function getActiveLoggers(): array
    {
        return array_keys($this->loggers);
    }

    /**
     * Establecer tenant manualmente (útil para contextos donde no hay request)
     */
    public function setCurrentTenant(string $tenant): void
    {
        $this->currentTenant = $tenant;
    }
}

// Incrementar temporalmente el límite de memoria
ini_set('memory_limit', '1G');