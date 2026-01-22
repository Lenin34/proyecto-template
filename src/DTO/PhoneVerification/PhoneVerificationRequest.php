<?php
namespace App\DTO\PhoneVerification;

use Symfony\Component\Validator\Constraints as Assert;

class PhoneVerificationRequest
{
    #[Assert\NotBlank(message: 'El código de verificación es obligatorio.')]
    #[Assert\Type(type: 'string', message: 'El código de verificación debe ser una cadena de texto.')]
    #[Assert\Length(min: 6, max: 6, exactMessage: 'El código de verificación debe tener exactamente {{ limit }} dígitos.')]
    public ?string $verification_code = null;
}