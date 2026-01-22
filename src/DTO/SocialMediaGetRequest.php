<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class SocialMediaGetRequest
{
    #[Assert\NotBlank(message: 'El ID de la empresa es obligatorio.')]
    public ?int $company_id = null;

    #[Assert\Type(type: 'integer', message: 'La cantidad de posts debe ser un número entero.')]
    public ?int $amount = null;

    #[Assert\Type(type: 'string', message: 'La fecha de inicio debe ser una cadena.')]
    #[Assert\Date(message: 'La fecha de inicio no es válida.')]
    public ?string $start_date = null;

    #[Assert\Type(type: 'string', message: 'La fecha de fin debe ser una cadena.')]
    #[Assert\Date(message: 'La fecha de fin no es válida.')]
    public ?string $end_date = null;

    #[Assert\Type(type: 'string', message: 'El estado debe ser una cadena.')]
    #[Assert\Choice(callback: ['App\Enum\Status', 'getValues'], message: 'El estado no es válido.')]
    public ?string $status = null;
}
