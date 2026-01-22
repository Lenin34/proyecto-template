<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class FormDebugService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Loguea todos los datos del formulario para debugging
     */
    public function logFormSubmission(Request $request, string $formId = null): array
    {
        $debugData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'form_id' => $formId,
            'method' => $request->getMethod(),
            'url' => $request->getUri(),
            'content_type' => $request->headers->get('Content-Type'),
            'content_length' => $request->headers->get('Content-Length'),
            'user_agent' => $request->headers->get('User-Agent'),
            'ip_address' => $request->getClientIp(),
        ];

        // Capturar datos POST
        $postData = $request->request->all();
        $debugData['post_data'] = $this->sanitizeFormData($postData);
        $debugData['post_data_count'] = count($postData);

        // Capturar datos JSON si existen
        $jsonContent = $request->getContent();
        if (!empty($jsonContent)) {
            try {
                $jsonData = json_decode($jsonContent, true);
                $debugData['json_data'] = $this->sanitizeFormData($jsonData);
                $debugData['json_raw_length'] = strlen($jsonContent);
            } catch (\Exception $e) {
                $debugData['json_error'] = $e->getMessage();
                $debugData['json_raw_preview'] = substr($jsonContent, 0, 200);
            }
        }

        // Analizar campos textarea específicamente
        $debugData['textarea_analysis'] = $this->analyzeTextareaFields($postData);

        // Capturar archivos si existen
        if ($request->files->count() > 0) {
            $debugData['files'] = [];
            foreach ($request->files->all() as $key => $file) {
                $debugData['files'][$key] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'type' => $file->getMimeType(),
                    'error' => $file->getError()
                ];
            }
        }

        $this->logger->info('[FORM_DEBUG] Form submission captured', $debugData);

        return $debugData;
    }

    /**
     * Analiza específicamente los campos textarea
     */
    private function analyzeTextareaFields(array $data): array
    {
        $textareaAnalysis = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value) && (strlen($value) > 100 || strpos($value, "\n") !== false)) {
                $textareaAnalysis[$key] = [
                    'length' => strlen($value),
                    'lines' => substr_count($value, "\n") + 1,
                    'has_special_chars' => $this->hasSpecialCharacters($value),
                    'encoding' => mb_detect_encoding($value),
                    'preview' => substr($value, 0, 100) . (strlen($value) > 100 ? '...' : ''),
                    'suspicious_patterns' => $this->detectSuspiciousPatterns($value)
                ];
            }
        }

        return $textareaAnalysis;
    }

    /**
     * Detecta caracteres especiales que podrían causar problemas
     */
    private function hasSpecialCharacters(string $value): array
    {
        $specialChars = [];
        
        // Caracteres que pueden causar problemas en bases de datos
        $problematicChars = [
            '\0' => 'null byte',
            '\x1a' => 'substitute character',
            '\r\n' => 'windows line ending',
            '\n' => 'unix line ending',
            '\r' => 'mac line ending',
            '"' => 'double quote',
            "'" => 'single quote',
            '\\' => 'backslash',
            '%' => 'percent sign',
            '_' => 'underscore'
        ];

        foreach ($problematicChars as $char => $description) {
            if (strpos($value, $char) !== false) {
                $specialChars[] = $description;
            }
        }

        return $specialChars;
    }

    /**
     * Detecta patrones sospechosos que podrían causar errores
     */
    private function detectSuspiciousPatterns(string $value): array
    {
        $patterns = [];

        // SQL injection patterns
        if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER)\b/i', $value)) {
            $patterns[] = 'possible_sql_injection';
        }

        // Script tags
        if (preg_match('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', $value)) {
            $patterns[] = 'script_tags';
        }

        // Extremely long lines
        $lines = explode("\n", $value);
        foreach ($lines as $line) {
            if (strlen($line) > 1000) {
                $patterns[] = 'very_long_line';
                break;
            }
        }

        // Binary data
        if (!mb_check_encoding($value, 'UTF-8')) {
            $patterns[] = 'non_utf8_encoding';
        }

        return $patterns;
    }

    /**
     * Sanitiza datos sensibles para logging
     */
    private function sanitizeFormData(array $data): array
    {
        $sanitized = [];
        $sensitiveFields = ['password', 'token', 'csrf', 'secret'];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;

            foreach ($sensitiveFields as $sensitiveField) {
                if (strpos($lowerKey, $sensitiveField) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeFormData($value);
            } elseif (is_string($value) && strlen($value) > 500) {
                $sanitized[$key] = substr($value, 0, 500) . '... [TRUNCATED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Loguea errores específicos de formularios
     */
    public function logFormError(\Exception $e, array $debugData = []): void
    {
        $errorData = [
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'stack_trace' => $e->getTraceAsString(),
            'debug_data' => $debugData
        ];

        $this->logger->error('[FORM_ERROR] Form processing failed', $errorData);
    }

    /**
     * Valida datos de textarea antes del procesamiento
     */
    public function validateTextareaData(array $data): array
    {
        $validationResults = [];

        foreach ($data as $key => $value) {
            if (is_string($value) && strlen($value) > 50) { // Posible textarea
                $validation = [
                    'field' => $key,
                    'is_valid' => true,
                    'issues' => []
                ];

                // Validar longitud
                if (strlen($value) > 65535) { // Límite típico de TEXT en MySQL
                    $validation['is_valid'] = false;
                    $validation['issues'][] = 'exceeds_text_limit';
                }

                // Validar encoding
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $validation['is_valid'] = false;
                    $validation['issues'][] = 'invalid_encoding';
                }

                // Validar caracteres nulos
                if (strpos($value, "\0") !== false) {
                    $validation['is_valid'] = false;
                    $validation['issues'][] = 'contains_null_bytes';
                }

                $validationResults[] = $validation;
            }
        }

        return $validationResults;
    }
}
