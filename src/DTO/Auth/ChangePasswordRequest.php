<?php
namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordRequest
{
    #[Assert\NotBlank(message: 'La contraseña actual es obligatoria.')]
    #[Assert\Length(
        min: 6,
        max: 50,
        minMessage: 'La contraseña actual debe tener al menos {{ limit }} caracteres.',
        maxMessage: 'La contraseña actual no puede tener más de {{ limit }} caracteres.'
    )]
    #[Assert\Type(type: 'string', message: 'La contraseña actual debe ser una cadena de texto.')]
    public ?string $current_password = null;

    #[Assert\NotBlank(message: 'La nueva contraseña es obligatoria.')]
    #[Assert\Length(
        min: 6,
        max: 50,
        minMessage: 'La nueva contraseña debe tener al menos {{ limit }} caracteres.',
        maxMessage: 'La nueva contraseña no puede tener más de {{ limit }} caracteres.'
    )]
    #[Assert\Type(type: 'string', message: 'La nueva contraseña debe ser una cadena de texto.')]
    public ?string $new_password = null;
}