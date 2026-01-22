<?php
namespace App\DTO\PhoneVerification;

use Symfony\Component\Validator\Constraints as Assert;

class PhoneNumberVerificationRequest
{
    #[Assert\NotBlank(message: 'El número de teléfono es obligatorio.')]
    #[Assert\Type(type: 'string', message: 'El número de teléfono debe ser una cadena de texto.')]
    #[Assert\Length(
        min: 10,
        max: 15,
        minMessage: 'El número de teléfono debe tener al menos {{ limit }} dígitos.',
        maxMessage: 'El número de teléfono no puede tener más de {{ limit }} dígitos.'
    )]
    #[Assert\Regex(
        pattern: '/^[0-9+\-\s()]+$/',
        message: 'El número de teléfono solo puede contener números, espacios, paréntesis, guiones y el símbolo +.'
    )]
    public ?string $phone_number = null;
}
