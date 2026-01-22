<?php

namespace App\DTO\Profile;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProfileUpdateRequest
{
    #[Assert\Email(message: 'El correo electrónico no es válido.')]
    #[Assert\Type(type: 'string', message: 'El correo electrónico debe ser una cadena de texto.')]
    public ?string $email = null;

    #[Assert\Type(type: 'string', message: 'El número de teléfono debe ser una cadena de texto.')]
    public ?string $phone_number = null;

    #[Assert\Type(type: 'string', message: 'El número de empleado debe ser una cadena de texto.')]
    public ?string $employee_number = null;

    #[Assert\Type(type: 'string', message: 'El CURP debe ser una cadena de texto.')]
    public ?string $curp = null;

    #[Assert\Type(type: 'integer', message: 'El ID de la empresa debe ser un número entero.')]
    public ?int $company_id = null;

    #[Assert\Type(type: 'string', message: 'El nombre debe ser una cadena de texto.')]
    public ?string $name = null;

    #[Assert\Type(type: 'string', message: 'El apellido debe ser una cadena de texto.')]
    public ?string $last_name = null;

    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/gif'],
        mimeTypesMessage: 'El archivo debe ser una imagen válida (JPEG, PNG o GIF).',
        maxSizeMessage: 'El tamaño del archivo no debe exceder los 5 MB.'
    )]
    public ?UploadedFile $photo = null;

    #[Assert\Type(type: 'string', message: 'El género debe ser una cadena de texto.')]
    public ?string $gender = null;

    #[Assert\Type(type: 'string', message: 'La educación debe ser una cadena de texto.')]
    public ?string $education = null;

    #[Assert\Date(message: 'La fecha de nacimiento no es válida.')]
    #[Assert\Type(type: 'string', message: 'La fecha de nacimiento debe ser una cadena de texto.')]
    public ?string $birthday = null;
}