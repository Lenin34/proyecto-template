<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class EventsGetRequest
{
    // Removido NotBlank - company_id es opcional para permitir consultas sin empresa
    #[Assert\Type(type: 'integer', message: 'El ID de la empresa debe ser un número entero.')]
    public ?int $company_id = null;

    #[Assert\Type(type: 'integer', message: 'La cantidad de eventos debe ser un número entero.')]
    public ?int $amount = null;

    #[Assert\Type(type: 'string', message: 'La fecha de inicio debe ser una cadena.')]
    #[Assert\Date(message: 'La fecha de inicio no es válida.')]
    public ?string $start_date = null;

    #[Assert\Type(type: 'string', message: 'La fecha de fin debe ser una cadena.')]
    #[Assert\Date(message: 'La fecha de fin no es válida.')]
    public ?string $end_date = null;
}
