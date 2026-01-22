<?php

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordRequest
{
    #[Assert\NotBlank(message: "La contraseña actual es obligatoria.")]
    public ?string $current_password = null;

    #[Assert\NotBlank(message: "La nueva contraseña es obligatoria.")]
    public ?string $new_password = null;
}
