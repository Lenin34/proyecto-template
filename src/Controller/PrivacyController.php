<?php

namespace App\Controller;

use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{dominio}/privacy-policy')]
final class PrivacyController extends AbstractController
{
    public function __construct(
        private readonly TenantManager $tenantManager
    ) {}

    #[Route('', name: 'app_privacy_policy', methods: ['GET'])]
    public function index(string $dominio): Response
    {
        try {
            // Configurar tenant para mantener consistencia con el sistema

            return $this->render('privacy/index.html.twig', [
                'dominio' => $dominio,
                'page_title' => 'Política de Privacidad'
            ]);
        } catch (\Exception $e) {
            // En caso de error, mostrar página básica de política de privacidad
            return $this->render('privacy/index.html.twig', [
                'dominio' => $dominio,
                'page_title' => 'Política de Privacidad',
                'basic_mode' => true
            ]);
        }
    }
}

