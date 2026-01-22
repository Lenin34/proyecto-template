<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

class ValidationService
{
    private ValidatorInterface $validator;
    private SerializerInterface $serializer;

    public function __construct(
        ValidatorInterface $validator,
        SerializerInterface $serializer
    ) {
        $this->validator = $validator;
        $this->serializer = $serializer;
    }

    /**
     * Valida un DTO y retorna los errores si los hay
     */
    public function validateDTO(object $dto): array
    {
        $violations = $this->validator->validate($dto);
        
        if (count($violations) > 0) {
            return $this->formatValidationErrors($violations);
        }

        return [];
    }

    /**
     * Crea y valida un DTO desde los datos del request
     */
    public function createAndValidateDTO(string $dtoClass, Request $request): array
    {
        try {
            $dto = $this->createDTOFromRequest($dtoClass, $request);
            $errors = $this->validateDTO($dto);
            
            return [
                'dto' => $dto,
                'errors' => $errors,
                'isValid' => empty($errors)
            ];
        } catch (\Exception $e) {
            return [
                'dto' => null,
                'errors' => ['general' => ['Error al procesar los datos: ' . $e->getMessage()]],
                'isValid' => false
            ];
        }
    }

    /**
     * Crea un DTO desde los datos del request
     */
    public function createDTOFromRequest(string $dtoClass, Request $request): object
    {
        $dto = new $dtoClass();
        $requestData = $request->request->all();

        // Mapear los datos del request al DTO
        foreach ($requestData as $key => $value) {
            $setterMethod = 'set' . ucfirst($this->camelCase($key));
            
            if (method_exists($dto, $setterMethod)) {
                // Convertir valores específicos
                $convertedValue = $this->convertValue($key, $value);
                $dto->$setterMethod($convertedValue);
            }
        }

        return $dto;
    }

    /**
     * Formatea los errores de validación en un array estructurado
     */
    private function formatValidationErrors(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        
        foreach ($violations as $violation) {
            $field = $violation->getPropertyPath();
            if (!isset($errors[$field])) {
                $errors[$field] = [];
            }
            $errors[$field][] = $violation->getMessage();
        }

        return $errors;
    }

    /**
     * Convierte snake_case a camelCase
     */
    private function camelCase(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    /**
     * Convierte valores específicos según el contexto
     */
    private function convertValue(string $key, $value)
    {
        // Convertir checkboxes a boolean
        if (in_array($key, ['required', 'multiple', 'is_required'])) {
            return $value === 'on' || $value === '1' || $value === true;
        }

        // Manejar arrays de IDs de empresas
        if ($key === 'companyIds' || $key === 'company_ids') {
            if (is_string($value)) {
                $value = explode(',', $value);
            }

            if (!is_array($value)) {
                return [];
            }

            // Filtrar y convertir a enteros
            return array_filter(array_map('intval', $value), function($id) {
                return $id > 0;
            });
        }

        // Convertir strings vacíos a null para campos opcionales
        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Obtiene los errores formateados para mostrar en la vista
     */
    public function getFormattedErrorsForView(array $errors): array
    {
        $formattedErrors = [];
        
        foreach ($errors as $field => $fieldErrors) {
            $formattedErrors[$field] = implode(', ', $fieldErrors);
        }

        return $formattedErrors;
    }

    /**
     * Verifica si un campo específico tiene errores
     */
    public function hasFieldError(array $errors, string $field): bool
    {
        return isset($errors[$field]) && !empty($errors[$field]);
    }

    /**
     * Obtiene el primer error de un campo específico
     */
    public function getFirstFieldError(array $errors, string $field): ?string
    {
        if ($this->hasFieldError($errors, $field)) {
            return $errors[$field][0];
        }

        return null;
    }

    /**
     * Valida múltiples DTOs y retorna un resumen de errores
     */
    public function validateMultipleDTOs(array $dtos): array
    {
        $allErrors = [];
        $isValid = true;

        foreach ($dtos as $index => $dto) {
            $errors = $this->validateDTO($dto);
            if (!empty($errors)) {
                $allErrors[$index] = $errors;
                $isValid = false;
            }
        }

        return [
            'errors' => $allErrors,
            'isValid' => $isValid,
            'totalErrors' => count($allErrors)
        ];
    }
}
