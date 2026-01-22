<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\ErrorResponseService;
use App\Enum\ErrorCodes\RequestValidatorErrorCodes;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RequestValidatorService
{
    private ValidatorInterface $validator;
    private ErrorResponseService $errorResponseService;
    private array $reflectionCache = [];

    public function __construct(ValidatorInterface $validator, ErrorResponseService $errorResponseService)
    {
        $this->validator = $validator;
        $this->errorResponseService = $errorResponseService;
    }

    public function validateAndMap(Request $request, string $dtoClass, bool $useQueryParams = false): object
    {
        if ($useQueryParams) {
            $data = $request->query->all();
        } elseif ($request->getContentTypeFormat() === 'form') {
            $data = array_merge($request->request->all(), $request->files->all());
        } else {
            $data = json_decode($request->getContent(), true);
        }

        if (!is_array($data)) {
            return $this->errorResponseService->createErrorResponse(RequestValidatorErrorCodes::REQUEST_VALIDATOR_INVALID_DATA_FORMAT,
                [
                    'data' => $data,
                ]
            );
        }

        $reflectionClass = $this->reflectionCache[$dtoClass] ??= new \ReflectionClass($dtoClass);
        $dto = new $dtoClass(); //TODO: Check if the class exists and is instantiable

        foreach ($data as $key => $value) {
            if ($reflectionClass->hasProperty($key)) {
                $property = $reflectionClass->getProperty($key);
                $propertyType = $property->getType();

                if ($propertyType) {
                    $typeName = $propertyType->getName();

                    switch($typeName) {
                        case UploadedFile::class:
                            if ($value instanceof UploadedFile) {
                                $dto->$key = $value;
                            }
                            break;
                        case 'int':
                            $dto->$key = is_numeric($value) ? (int)$value : null;
                            break;
                        case 'float':
                            $dto->$key = is_numeric($value) ? (float)$value : null;
                            break;
                        case 'bool':
                            $dto->$key = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                            break;
                        case 'string':
                            // Normalizar fechas si el campo contiene 'date' en el nombre
                            if (is_string($value) && $this->isDateField($key)) {
                                $originalValue = $value;
                                $normalizedValue = $this->normalizeDateString($value);
                                if ($originalValue !== $normalizedValue) {
                                    error_log("Date normalized: '{$key}' from '{$originalValue}' to '{$normalizedValue}'");
                                }
                                $dto->$key = $normalizedValue;
                            } else {
                                $dto->$key = is_string($value) ? $value : null;
                            }
                            break;
                        case 'array':
                            $dto->$key = is_array($value) ? $value : null;
                            break;
                        default:
                            $dto->$key = $value;
                            break;
                    }
                } else {
                    $dto->$key = $value;
                }
            }
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = array_map(fn($error) => $error->getMessage(), iterator_to_array($errors));
            return $this->errorResponseService->createErrorResponse(RequestValidatorErrorCodes::REQUEST_VALIDATOR_VALIDATION_ERROR, 
                [
                    'errors' => $errorMessages,
                ]
            );
        }

        return $dto;
    }

    /**
     * Determina si un campo es un campo de fecha basándose en su nombre
     */
    private function isDateField(string $fieldName): bool
    {
        $dateFieldPatterns = [
            'date',
            'start_date',
            'end_date',
            'created_at',
            'updated_at',
            'birthday',
            'birth_date',
            'effective_from',
            'effective_until',
            'attendance_date'
        ];

        $fieldNameLower = strtolower($fieldName);

        foreach ($dateFieldPatterns as $pattern) {
            if (strpos($fieldNameLower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normaliza una cadena de fecha al formato Y-m-d
     */
    private function normalizeDateString(string $dateString): string
    {
        // Si la cadena está vacía o es null, retornar tal como está
        if (empty(trim($dateString))) {
            return $dateString;
        }

        // Intentar varios formatos de fecha comunes
        $formats = [
            'Y-m-d',     // 2025-06-01 (ya válido)
            'Y-n-j',     // 2025-6-1 (mes y día sin ceros)
            'Y-m-j',     // 2025-06-1 (día sin cero)
            'Y-n-d',     // 2025-6-01 (mes sin cero)
            'd/m/Y',     // 01/06/2025 (formato europeo)
            'm/d/Y',     // 06/01/2025 (formato americano)
            'd-m-Y',     // 01-06-2025 (formato europeo con guiones)
            'm-d-Y',     // 06-01-2025 (formato americano con guiones)
            'Y/m/d',     // 2025/06/01 (con barras)
            'Y/n/j',     // 2025/6/1 (con barras sin ceros)
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, trim($dateString));

            // Verificar que la fecha se creó correctamente y no hay errores de parsing
            if ($date && $date->format($format) === trim($dateString)) {
                return $date->format('Y-m-d');
            }
        }

        // Si no se pudo parsear con ningún formato, intentar con strtotime como último recurso
        $timestamp = strtotime(trim($dateString));
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        // Si todo falla, retornar la cadena original para que la validación la rechace
        return $dateString;
    }
}