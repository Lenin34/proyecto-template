<?php

namespace App\DTO\Form;

use Symfony\Component\Validator\Constraints as Assert;

class FormFieldUpdateRequest
{
    #[Assert\NotBlank(message: 'La etiqueta del campo es obligatoria.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'La etiqueta debe tener al menos {{ limit }} caracteres.',
        maxMessage: 'La etiqueta no puede tener más de {{ limit }} caracteres.'
    )]
    #[Assert\Type(type: 'string', message: 'La etiqueta debe ser una cadena de texto.')]
    public ?string $label = null;

    #[Assert\NotBlank(message: 'El nombre del campo es obligatorio.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'El nombre debe tener al menos {{ limit }} caracteres.',
        maxMessage: 'El nombre no puede tener más de {{ limit }} caracteres.'
    )]
    #[Assert\Type(type: 'string', message: 'El nombre debe ser una cadena de texto.')]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z][a-zA-Z0-9_]*$/',
        message: 'El nombre debe comenzar con una letra y solo puede contener letras, números y guiones bajos.'
    )]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'El tipo de campo es obligatorio.')]
    #[Assert\Choice(
        choices: ['text', 'number', 'textarea', 'select', 'checkbox', 'radio', 'date', 'file'],
        message: 'El tipo de campo debe ser uno de los siguientes: {{ choices }}'
    )]
    public ?string $type = null;

    #[Assert\Type(type: 'bool', message: 'El campo requerido debe ser verdadero o falso.')]
    public ?bool $isRequired = false;

    #[Assert\Length(
        max: 2000,
        maxMessage: 'Las opciones no pueden tener más de {{ limit }} caracteres.'
    )]
    #[Assert\Type(type: 'string', message: 'Las opciones deben ser una cadena de texto.')]
    public ?string $options = null;

    #[Assert\Length(
        max: 500,
        maxMessage: 'El texto de ayuda no puede tener más de {{ limit }} caracteres.'
    )]
    #[Assert\Type(type: 'string', message: 'El texto de ayuda debe ser una cadena de texto.')]
    public ?string $help = null;

    #[Assert\Type(type: 'bool', message: 'El campo múltiple debe ser verdadero o falso.')]
    public ?bool $multiple = false;

    #[Assert\Choice(
        choices: ['col-md-3', 'col-md-4', 'col-md-6', 'col-md-12'],
        message: 'El ancho de columna debe ser uno de los siguientes: {{ choices }}'
    )]
    #[Assert\Type(type: 'string', message: 'El ancho de columna debe ser una cadena de texto.')]
    public ?string $cols = null;

    #[Assert\Type(type: 'string', message: 'Las filas del textarea deben ser una cadena de texto.')]
    #[Assert\Regex(
        pattern: '/^[1-9][0-9]?$|^20$/',
        message: 'Las filas del textarea deben ser un número entre 1 y 20.'
    )]
    public ?string $textareaCols = null;

    // Getters y Setters
    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getIsRequired(): ?bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(?bool $isRequired): self
    {
        $this->isRequired = $isRequired;
        return $this;
    }

    public function getOptions(): ?string
    {
        return $this->options;
    }

    public function setOptions(?string $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function getHelp(): ?string
    {
        return $this->help;
    }

    public function setHelp(?string $help): self
    {
        $this->help = $help;
        return $this;
    }

    public function getMultiple(): ?bool
    {
        return $this->multiple;
    }

    public function setMultiple(?bool $multiple): self
    {
        $this->multiple = $multiple;
        return $this;
    }

    public function getCols(): ?string
    {
        return $this->cols;
    }

    public function setCols(?string $cols): self
    {
        $this->cols = $cols;
        return $this;
    }

    public function getTextareaCols(): ?string
    {
        return $this->textareaCols;
    }

    public function setTextareaCols(?string $textareaCols): self
    {
        $this->textareaCols = $textareaCols;
        return $this;
    }

    /**
     * Validación personalizada para opciones según el tipo de campo
     */
    #[Assert\Callback]
    public function validateOptions(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if (in_array($this->type, ['select', 'checkbox', 'radio']) && empty($this->options)) {
            $context->buildViolation('Las opciones son obligatorias para campos de tipo select, checkbox o radio.')
                ->atPath('options')
                ->addViolation();
        }
    }

    /**
     * Validación personalizada para múltiple según el tipo de campo
     */
    #[Assert\Callback]
    public function validateMultiple(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->multiple && !in_array($this->type, ['select', 'file'])) {
            $context->buildViolation('La selección múltiple solo está disponible para campos de tipo select o file.')
                ->atPath('multiple')
                ->addViolation();
        }
    }
}
