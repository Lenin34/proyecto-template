<?php

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class GoogleLoginRequest
{
    #[Assert\NotBlank(message: 'El token de Google es obligatorio.')]
    #[Assert\Type(type: 'string', message: 'El token de Google debe ser una cadena de texto.')]
    public ?string $id_token = null;
}
