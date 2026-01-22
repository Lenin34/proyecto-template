<?php

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class ForgotPasswordResetRequest
{
    #[Assert\NotBlank(message: "La nueva contraseña es obligatoria.")]
    public ?string $new_password = null;
}
