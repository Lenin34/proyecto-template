<?php
namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class DeviceTokenRequest
{
    #[Assert\NotBlank(message: 'El token del dispositivo es obligatorio.')]
    #[Assert\Type(type: 'string', message: 'El token del dispositivo debe ser una cadena de texto.')]
    public ?string $device_token = null;
}