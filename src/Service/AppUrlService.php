<?php

// src/Service/AppUrlService.php
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AppUrlService
{
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function getBaseUrl(): string
    {
        $env = $this->params->get('app.env');
        return $env === 'dev'
            ? $this->params->get('app.url.dev')
            : $this->params->get('app.url.prod');
    }
}