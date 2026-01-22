<?php

namespace App\Service;

use App\Entity\App\FormTemplateField;

class FormPreviewService
{
    private FormTemplateService $formTemplateService;
    private FormFieldService $formFieldService;

    public function __construct(
        FormTemplateService $formTemplateService,
        FormFieldService $formFieldService
    ) {
        $this->formTemplateService = $formTemplateService;
        $this->formFieldService = $formFieldService;
    }

    /**
     * Genera la vista previa HTML de un formulario
     */
    public function generatePreviewHtml(int $formTemplateId): array
    {
        $formTemplate = $this->formTemplateService->getFormTemplateById($formTemplateId);
        $fields = $this->formFieldService->getActiveFieldsByFormTemplate($formTemplate);

        return [
            'form_template' => [
                'id' => $formTemplate->getId(),
                'name' => $formTemplate->getName(),
                'description' => $formTemplate->getDescription()
            ],
            'fields' => array_map([$this, 'formatFieldForPreview'], $fields),
            'validation_rules' => $this->generateValidationRules($fields),
            'css_classes' => $this->generateCssClasses($fields)
        ];
    }

    /**
     * Genera reglas de validación JavaScript para el formulario
     */
    public function generateValidationRules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $fieldRules = [];

            // Validación de campo requerido
            if ($field->getIsRequired()) {
                $fieldRules['required'] = true;
                $fieldRules['messages']['required'] = "El campo '{$field->getLabel()}' es obligatorio.";
            }

            // Validaciones específicas por tipo
            switch ($field->getType()) {
                case 'text':
                    if (strpos(strtolower($field->getName()), 'email') !== false) {
                        $fieldRules['email'] = true;
                        $fieldRules['messages']['email'] = "Ingrese un email válido.";
                    }
                    if (strpos(strtolower($field->getName()), 'phone') !== false) {
                        $fieldRules['pattern'] = '^[+]?[0-9\s\-\(\)]+$';
                        $fieldRules['messages']['pattern'] = "Ingrese un número de teléfono válido.";
                    }
                    break;

                case 'number':
                    $fieldRules['number'] = true;
                    $fieldRules['messages']['number'] = "Ingrese un número válido.";
                    break;

                case 'date':
                    $fieldRules['date'] = true;
                    $fieldRules['messages']['date'] = "Ingrese una fecha válida.";
                    break;

                case 'textarea':
                    if ($field->getIsRequired()) {
                        $fieldRules['minlength'] = 10;
                        $fieldRules['messages']['minlength'] = "Ingrese al menos 10 caracteres.";
                    }
                    break;

                case 'file':
                    $fieldRules['accept'] = 'image/*,.pdf,.doc,.docx';
                    $fieldRules['messages']['accept'] = "Tipo de archivo no permitido.";
                    break;
            }

            if (!empty($fieldRules)) {
                $rules[$field->getName()] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Genera clases CSS personalizadas para el formulario
     */
    public function generateCssClasses(array $fields): array
    {
        $classes = [
            'form' => 'preview-form',
            'field_wrapper' => 'mb-3',
            'label' => 'form-label',
            'input' => 'form-control',
            'select' => 'form-select',
            'textarea' => 'form-control',
            'checkbox' => 'form-check-input',
            'radio' => 'form-check-input',
            'file' => 'form-control',
            'required_indicator' => 'text-danger',
            'help_text' => 'form-text text-muted',
            'error' => 'invalid-feedback',
            'submit_button' => 'btn btn-primary'
        ];

        return $classes;
    }

    /**
     * Formatea un campo para la vista previa
     */
    private function formatFieldForPreview(FormTemplateField $field): array
    {
        $fieldData = [
            'id' => $field->getId(),
            'name' => $field->getName(),
            'label' => $field->getLabel(),
            'type' => $field->getType(),
            'required' => $field->getIsRequired(),
            'help' => $field->getHelp(),
            'multiple' => $field->getMultiple(),
            'position' => $field->getPosition(),
            'cols' => $field->getCols() ?: 'col-12',
            'textarea_cols' => $field->getTextareaCols() ?: 3
        ];

        // Procesar opciones para select, radio y checkbox
        if (in_array($field->getType(), ['select', 'radio', 'checkbox']) && $field->getOptions()) {
            $fieldData['options'] = array_map('trim', explode(',', $field->getOptions()));
        }

        // Generar atributos HTML específicos
        $fieldData['attributes'] = $this->generateFieldAttributes($field);

        return $fieldData;
    }

    /**
     * Genera atributos HTML para un campo
     */
    private function generateFieldAttributes(FormTemplateField $field): array
    {
        $attributes = [
            'id' => $field->getName(),
            'name' => $field->getName(),
            'class' => 'form-control'
        ];

        if ($field->getIsRequired()) {
            $attributes['required'] = 'required';
        }

        switch ($field->getType()) {
            case 'text':
                if (strpos(strtolower($field->getName()), 'email') !== false) {
                    $attributes['type'] = 'email';
                    $attributes['placeholder'] = 'ejemplo@correo.com';
                } elseif (strpos(strtolower($field->getName()), 'phone') !== false) {
                    $attributes['type'] = 'tel';
                    $attributes['placeholder'] = '+1 234 567 8900';
                } else {
                    $attributes['type'] = 'text';
                    $attributes['placeholder'] = 'Ingrese ' . strtolower($field->getLabel());
                }
                break;

            case 'number':
                $attributes['type'] = 'number';
                $attributes['placeholder'] = 'Ingrese un número';
                break;

            case 'date':
                $attributes['type'] = 'date';
                break;

            case 'textarea':
                unset($attributes['type']);
                $attributes['rows'] = $field->getTextareaCols() ?: 3;
                $attributes['placeholder'] = 'Ingrese ' . strtolower($field->getLabel());
                break;

            case 'select':
                $attributes['class'] = 'form-select';
                break;

            case 'checkbox':
            case 'radio':
                $attributes['class'] = 'form-check-input';
                break;

            case 'file':
                $attributes['type'] = 'file';
                $attributes['class'] = 'form-control';
                if ($field->getMultiple()) {
                    $attributes['multiple'] = 'multiple';
                }
                break;
        }

        return $attributes;
    }

    /**
     * Valida los datos enviados contra las reglas del formulario
     */
    public function validateFormData(int $formTemplateId, array $data): array
    {
        $formTemplate = $this->formTemplateService->getFormTemplateById($formTemplateId);
        $fields = $this->formFieldService->getActiveFieldsByFormTemplate($formTemplate);

        $errors = [];
        $validatedData = [];

        foreach ($fields as $field) {
            $fieldName = $field->getName();
            $fieldValue = $data[$fieldName] ?? null;

            // Validar campo requerido
            if ($field->getIsRequired() && empty($fieldValue)) {
                $errors[$fieldName][] = "El campo '{$field->getLabel()}' es obligatorio.";
                continue;
            }

            // Si el campo no es requerido y está vacío, continuar
            if (empty($fieldValue)) {
                $validatedData[$fieldName] = null;
                continue;
            }

            // Validaciones específicas por tipo
            $fieldErrors = $this->validateFieldValue($field, $fieldValue);
            if (!empty($fieldErrors)) {
                $errors[$fieldName] = array_merge($errors[$fieldName] ?? [], $fieldErrors);
            } else {
                $validatedData[$fieldName] = $fieldValue;
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'validated_data' => $validatedData
        ];
    }

    /**
     * Valida el valor de un campo específico
     */
    private function validateFieldValue(FormTemplateField $field, $value): array
    {
        $errors = [];

        switch ($field->getType()) {
            case 'text':
                if (strpos(strtolower($field->getName()), 'email') !== false) {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Ingrese un email válido.";
                    }
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    $errors[] = "Ingrese un número válido.";
                }
                break;

            case 'date':
                if (!strtotime($value)) {
                    $errors[] = "Ingrese una fecha válida.";
                }
                break;

            case 'select':
            case 'radio':
                if ($field->getOptions()) {
                    $validOptions = array_map('trim', explode(',', $field->getOptions()));
                    if (!in_array($value, $validOptions)) {
                        $errors[] = "Seleccione una opción válida.";
                    }
                }
                break;

            case 'checkbox':
                if ($field->getOptions() && is_array($value)) {
                    $validOptions = array_map('trim', explode(',', $field->getOptions()));
                    foreach ($value as $selectedValue) {
                        if (!in_array($selectedValue, $validOptions)) {
                            $errors[] = "Una o más opciones seleccionadas no son válidas.";
                            break;
                        }
                    }
                }
                break;
        }

        return $errors;
    }

    /**
     * Genera JavaScript para validación en tiempo real
     */
    public function generateValidationScript(array $validationRules): string
    {
        $script = "
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.preview-form');
            if (!form) return;

            const rules = " . json_encode($validationRules) . ";

            // Validación en tiempo real
            Object.keys(rules).forEach(fieldName => {
                const field = form.querySelector('[name=\"' + fieldName + '\"]');
                if (!field) return;

                field.addEventListener('blur', function() {
                    validateField(fieldName, field.value, rules[fieldName]);
                });

                field.addEventListener('input', function() {
                    clearFieldError(fieldName);
                });
            });

            // Validación al enviar
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                let isValid = true;

                Object.keys(rules).forEach(fieldName => {
                    const field = form.querySelector('[name=\"' + fieldName + '\"]');
                    if (field && !validateField(fieldName, field.value, rules[fieldName])) {
                        isValid = false;
                    }
                });

                if (isValid) {
                    alert('Formulario válido! (Esta es solo una vista previa)');
                }
            });

            function validateField(fieldName, value, fieldRules) {
                clearFieldError(fieldName);
                
                if (fieldRules.required && (!value || value.trim() === '')) {
                    showFieldError(fieldName, fieldRules.messages.required);
                    return false;
                }

                if (fieldRules.email && value && !isValidEmail(value)) {
                    showFieldError(fieldName, fieldRules.messages.email);
                    return false;
                }

                if (fieldRules.number && value && isNaN(value)) {
                    showFieldError(fieldName, fieldRules.messages.number);
                    return false;
                }

                return true;
            }

            function showFieldError(fieldName, message) {
                const field = form.querySelector('[name=\"' + fieldName + '\"]');
                if (!field) return;

                field.classList.add('is-invalid');
                
                let errorDiv = field.parentNode.querySelector('.invalid-feedback');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    field.parentNode.appendChild(errorDiv);
                }
                errorDiv.textContent = message;
            }

            function clearFieldError(fieldName) {
                const field = form.querySelector('[name=\"' + fieldName + '\"]');
                if (!field) return;

                field.classList.remove('is-invalid');
                const errorDiv = field.parentNode.querySelector('.invalid-feedback');
                if (errorDiv) {
                    errorDiv.remove();
                }
            }

            function isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }
        });
        ";

        return $script;
    }
}
