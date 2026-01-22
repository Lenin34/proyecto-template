<?php
namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class RegistrationRequest
{
/*    #[Assert\NotBlank(message: 'El ID de la empresa es obligatorio.')]
    #[Assert\Type(type: 'integer', message: 'El ID de la empresa debe ser un número entero.')]
    public ?int $company_id = null;*/

    #[Assert\NotBlank(message: 'El número de empleado es obligatorio.')]
    #[Assert\Type(type: 'string', message: 'El número de empleado debe ser una cadena de texto.')]
    public ?string $employee_number = null;

    #[Assert\NotBlank(message: 'El correo electrónico es obligatorio.')]
    #[Assert\Email(message: 'El correo electrónico no es válido.')]
    #[Assert\Type(type: 'string', message: 'El correo electrónico debe ser una cadena de texto.')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'El número de teléfono es obligatorio.')]
    #[Assert\Type(type: 'string', message: 'El número de teléfono debe ser una cadena de texto.')]
    public ?string $phone_number = null;

    #[Assert\NotBlank(message: 'El CURP es obligatorio.')]
    #[Assert\Type(type: 'string', message: 'El CURP debe ser una cadena de texto.')]
    public ?string $curp = null;

    #[Assert\NotBlank(message: 'La contraseña es obligatoria.')]
    #[Assert\Length(
        min: 6,
        max: 50,
        minMessage: 'La contraseña debe tener al menos {{ limit }} caracteres.',
        maxMessage: 'La contraseña no puede tener más de {{ limit }} caracteres.'
    )]
    #[Assert\Type(type: 'string', message: 'La contraseña debe ser una cadena de texto.')]
    public ?string $password = null;
}