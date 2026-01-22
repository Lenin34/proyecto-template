<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class BenefitsGetRequest
{
    #[Assert\Type(type: 'integer', message: 'El ID de la empresa debe ser un número entero.')]
    public ?int $company_id = null;

    #[Assert\Positive]
    #[Assert\Type(type: 'integer', message: 'La cantidad por página debe ser un número entero.')]
    public ?int $per_page = null;

    #[Assert\Positive]
    #[Assert\Type(type: 'integer', message: 'La página debe ser un número entero.')]
    public ?int $page = null;
}