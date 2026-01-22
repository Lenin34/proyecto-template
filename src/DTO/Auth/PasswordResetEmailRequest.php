<?php

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class PasswordResetEmailRequest
{
    #[Assert\NotBlank(message: "El email es obligatorio.")]
    #[Assert\Email(message: "El formato del email no es válido.")]
    public ?string $email = null;
}
