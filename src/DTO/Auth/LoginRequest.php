<?php
namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class LoginRequest
{
    #[Assert\Email(message: 'El correo electrónico no es válido.')]
    #[Assert\Type(type: 'string', message: 'El correo electrónico debe ser una cadena de texto.')]
    public ?string $email = null;

    #[Assert\Length(min: 10, max: 10)]
    #[Assert\Type(type: 'string')]
    public ?string $phone_number = null;

    #[Assert\NotBlank(message: 'La contraseña es obligatoria.')]
    #[Assert\Length(
        min: 6,
        max: 50,
        minMessage: 'La contraseña debe tener al menos {{ limit }} caracteres.',
        maxMessage: 'La contraseña no puede tener más de {{ limit }} caracteres.'
    )]
    #[Assert\Type(type: 'string', message: 'La contraseña debe ser una cadena de texto.')]
    public ?string $password = null;

    #[Assert\Callback]
    public function validateIdentifier(ExecutionContextInterface $context): void
    {
        if (empty($this->email) && empty($this->phone_number)) {
            $context->buildViolation('Debes ingresar un correo electrónico o un número de teléfono.')
                ->atPath('email')
                ->addViolation();
        }

        if (!empty($this->email) && !empty($this->phone_number)) {
            $context->buildViolation('Solo puedes ingresar correo electrónico **o** número de teléfono, no ambos.')
                ->atPath('email')
                ->addViolation();
        }
    }
}