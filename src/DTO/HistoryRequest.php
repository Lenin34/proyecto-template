<?php
namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class HistoryRequest
{
    #[Assert\NotBlank(message: 'El nombre del evento es obligatorio.')]
    #[Assert\Type(type: 'string', message: 'El nombre del evento debe ser una cadena de texto.')]
    public ?string $event_name = null;
}