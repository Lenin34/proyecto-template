<?php

namespace App\DTO\Form;

use Symfony\Component\Validator\Constraints as Assert;

class FormTemplateCreateRequest
{
    #[Assert\NotBlank(message: 'El nombre del formulario es obligatorio.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'El nombre debe tener al menos {{ limit }} caracteres.',
        maxMessage: 'El nombre no puede tener más de {{ limit }} caracteres.'
    )]
    #[Assert\Type(type: 'string', message: 'El nombre debe ser una cadena de texto.')]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9\s\-_áéíóúÁÉÍÓÚñÑ]+$/',
        message: 'El nombre solo puede contener letras, números, espacios, guiones y guiones bajos.'
    )]
    public ?string $name = null;

    #[Assert\Length(
        max: 1000,
        maxMessage: 'La descripción no puede tener más de {{ limit }} caracteres.'
    )]
    #[Assert\Type(type: 'string', message: 'La descripción debe ser una cadena de texto.')]
    public ?string $description = null;

    public array $companyIds = [];

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCompanyIds(): array
    {
        return $this->companyIds;
    }

    public function setCompanyIds(array $companyIds): self
    {
        $this->companyIds = $companyIds;
        return $this;
    }
}
