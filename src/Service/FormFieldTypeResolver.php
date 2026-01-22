<?php

namespace App\Service;

use App\Entity\App\FormTemplateField;
use Psr\Log\LoggerInterface;

/**
 * Servicio para resolver inconsistencias en tipos de campos de formularios
 */
class FormFieldTypeResolver
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Resuelve el tipo real de un campo basado en sus propiedades
     */
    public function resolveFieldType(FormTemplateField $field): string
    {
        $originalType = $field->getType();
        $resolvedType = $this->determineActualType($field);

        if ($originalType !== $resolvedType) {
            $this->logger->warning('[FIELD_TYPE_MISMATCH] Field type inconsistency detected', [
                'field_id' => $field->getId(),
                'field_name' => $field->getName(),
                'original_type' => $originalType,
                'resolved_type' => $resolvedType,
                'textarea_cols' => $field->getTextareaCols(),
                'has_options' => !empty($field->getOptions()),
                'is_multiple' => $field->isMultiple()
            ]);
        }

        return $resolvedType;
    }

    /**
     * Determina el tipo real del campo basado en sus propiedades
     */
    private function determineActualType(FormTemplateField $field): string
    {
        $type = $field->getType();
        $textareaCols = $field->getTextareaCols();
        $options = $field->getOptions();
        $isMultiple = $field->isMultiple();

        // Prioridad 1: Si tiene opciones, debe ser un campo de selección
        if (!empty($options)) {
            $optionsList = array_filter(array_map('trim', explode(',', $options)));
            if (count($optionsList) > 0) {
                // Si es múltiple y tiene opciones, probablemente es checkbox o select múltiple
                if ($isMultiple) {
                    if ($type === 'select') {
                        return 'select'; // Select múltiple
                    } else {
                        return 'checkbox';
                    }
                } else {
                    // Si no es múltiple, puede ser radio o select simple
                    if (count($optionsList) <= 5) {
                        return 'radio'; // Pocas opciones = radio
                    } else {
                        return 'select'; // Muchas opciones = select
                    }
                }
            }
        }

        // Prioridad 2: Si tiene textarea_cols configurado Y no tiene opciones, es textarea
        if (!empty($textareaCols) && $textareaCols > 0 && empty($options)) {
            if ($type !== 'textarea') {
                $this->logger->info('[FIELD_TYPE_CORRECTION] Correcting field type to textarea', [
                    'field_id' => $field->getId(),
                    'original_type' => $type,
                    'textarea_cols' => $textareaCols
                ]);
                return 'textarea';
            }
        }



        // Validar tipos específicos
        switch ($type) {
            case 'email':
            case 'password':
            case 'number':
            case 'date':
            case 'time':
            case 'datetime-local':
            case 'url':
            case 'tel':
                return $type;
                
            case 'file':
                return 'file';
                
            case 'hidden':
                return 'hidden';
                
            case 'textarea':
                return 'textarea';
                
            case 'select':
            case 'radio':
            case 'checkbox':
                // Ya se validaron arriba con las opciones
                return $type;
                
            default:
                // Por defecto, es text
                return 'text';
        }
    }

    /**
     * Valida la consistencia de un campo y sugiere correcciones
     */
    public function validateFieldConsistency(FormTemplateField $field): array
    {
        $issues = [];
        $suggestions = [];

        $type = $field->getType();
        $textareaCols = $field->getTextareaCols();
        $options = $field->getOptions();
        $isMultiple = $field->isMultiple();

        // Validar textarea
        if ($type === 'textarea') {
            if (empty($textareaCols) || $textareaCols <= 0) {
                $issues[] = 'Textarea sin configuración de filas';
                $suggestions[] = 'Configurar textarea_cols con un valor entre 3 y 10';
            }
        } else if (!empty($textareaCols) && $textareaCols > 0) {
            $issues[] = 'Campo con textarea_cols pero tipo diferente a textarea';
            $suggestions[] = 'Cambiar tipo a textarea o remover textarea_cols';
        }

        // Validar campos con opciones
        if (in_array($type, ['select', 'radio', 'checkbox'])) {
            if (empty($options)) {
                $issues[] = 'Campo de selección sin opciones';
                $suggestions[] = 'Agregar opciones separadas por comas';
            }
        } else if (!empty($options)) {
            $issues[] = 'Campo con opciones pero tipo que no las usa';
            $suggestions[] = 'Cambiar tipo a select, radio o checkbox';
        }

        // Validar múltiple
        if ($isMultiple && !in_array($type, ['select', 'checkbox', 'file'])) {
            $issues[] = 'Campo marcado como múltiple pero tipo incompatible';
            $suggestions[] = 'Solo select, checkbox y file pueden ser múltiples';
        }

        return [
            'is_consistent' => empty($issues),
            'issues' => $issues,
            'suggestions' => $suggestions
        ];
    }

    /**
     * Corrige automáticamente un campo inconsistente
     */
    public function autoCorrectField(FormTemplateField $field): bool
    {
        $originalType = $field->getType();
        $correctedType = $this->resolveFieldType($field);
        
        if ($originalType !== $correctedType) {
            $field->setType($correctedType);
            
            $this->logger->info('[FIELD_AUTO_CORRECTED]', [
                'field_id' => $field->getId(),
                'field_name' => $field->getName(),
                'from_type' => $originalType,
                'to_type' => $correctedType
            ]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Analiza todos los campos de un formulario
     */
    public function analyzeFormFields(array $fields): array
    {
        $analysis = [
            'total_fields' => count($fields),
            'inconsistent_fields' => [],
            'corrected_fields' => [],
            'field_types' => [],
            'issues_summary' => []
        ];

        foreach ($fields as $field) {
            if (!$field instanceof FormTemplateField) {
                continue;
            }

            $validation = $this->validateFieldConsistency($field);
            $fieldType = $this->resolveFieldType($field);

            // Contar tipos
            if (!isset($analysis['field_types'][$fieldType])) {
                $analysis['field_types'][$fieldType] = 0;
            }
            $analysis['field_types'][$fieldType]++;

            // Registrar campos inconsistentes
            if (!$validation['is_consistent']) {
                $analysis['inconsistent_fields'][] = [
                    'field_id' => $field->getId(),
                    'field_name' => $field->getName(),
                    'current_type' => $field->getType(),
                    'resolved_type' => $fieldType,
                    'issues' => $validation['issues'],
                    'suggestions' => $validation['suggestions']
                ];

                // Agregar a resumen de problemas
                foreach ($validation['issues'] as $issue) {
                    if (!isset($analysis['issues_summary'][$issue])) {
                        $analysis['issues_summary'][$issue] = 0;
                    }
                    $analysis['issues_summary'][$issue]++;
                }
            }
        }

        return $analysis;
    }

    /**
     * Genera reporte de inconsistencias
     */
    public function generateInconsistencyReport(array $fields): string
    {
        $analysis = $this->analyzeFormFields($fields);
        
        $report = "=== REPORTE DE INCONSISTENCIAS DE CAMPOS ===\n\n";
        $report .= "Total de campos: {$analysis['total_fields']}\n";
        $report .= "Campos inconsistentes: " . count($analysis['inconsistent_fields']) . "\n\n";

        if (!empty($analysis['field_types'])) {
            $report .= "DISTRIBUCIÓN DE TIPOS:\n";
            foreach ($analysis['field_types'] as $type => $count) {
                $report .= "- $type: $count\n";
            }
            $report .= "\n";
        }

        if (!empty($analysis['issues_summary'])) {
            $report .= "PROBLEMAS MÁS COMUNES:\n";
            arsort($analysis['issues_summary']);
            foreach ($analysis['issues_summary'] as $issue => $count) {
                $report .= "- $issue: $count campos\n";
            }
            $report .= "\n";
        }

        if (!empty($analysis['inconsistent_fields'])) {
            $report .= "CAMPOS CON PROBLEMAS:\n";
            foreach ($analysis['inconsistent_fields'] as $field) {
                $report .= "\nCampo: {$field['field_name']} (ID: {$field['field_id']})\n";
                $report .= "  Tipo actual: {$field['current_type']}\n";
                $report .= "  Tipo sugerido: {$field['resolved_type']}\n";
                $report .= "  Problemas:\n";
                foreach ($field['issues'] as $issue) {
                    $report .= "    - $issue\n";
                }
                $report .= "  Sugerencias:\n";
                foreach ($field['suggestions'] as $suggestion) {
                    $report .= "    - $suggestion\n";
                }
            }
        }

        return $report;
    }
}
