<?php
namespace App\DTO\Beneficiary;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BeneficiaryUpdateRequest
{
    #[Assert\Type(type: 'string', message: 'El nombre debe ser un valor de tipo cadena de texto.')]
    public ?string $name = null;

    #[Assert\Type(type: 'string', message: 'El apellido debe ser un valor de tipo cadena de texto.')]
    public ?string $last_name = null;

    #[Assert\Type(type: 'string', message: 'El apellido materno debe ser un valor de tipo cadena de texto.')]
    public ?string $maternal_last_name = null;

    #[Assert\Type(type: 'string', message: 'El parentesco debe ser un valor de tipo cadena de texto.')]
    public ?string $kinship = null;

    #[Assert\Type(type: 'string', message: 'El CURP debe ser un valor de tipo cadena de texto.')]
    public ?string $curp = null;

    public ?UploadedFile $photo = null;

    #[Assert\Type(type: 'string', message: 'El género debe ser un valor de tipo cadena de texto.')]
    public ?string $gender = null;

    #[Assert\Type(type: 'string', message: 'La educación debe ser un valor de tipo cadena de texto.')]
    public ?string $education = null;

    #[Assert\Date(message: 'La fecha de nacimiento no es válida.')]
    #[Assert\Type(type: 'string', message: 'La fecha de nacimiento debe ser un valor de tipo cadena de texto.')]
    public ?string $birthday = null;
}