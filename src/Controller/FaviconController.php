<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FaviconController extends AbstractController
{
    /**
     * Ruta explícita para favicon.ico con alta prioridad
     * CRÍTICO: Debe tener prioridad sobre la ruta comodín /{dominio} para evitar
     * que favicon.ico sea interpretado como un tenant y cause pérdida de sesión
     */
    #[Route('/favicon.ico', name: 'app_favicon', methods: ['GET'], priority: 100)]
    public function icon(): Response
    {
        // Return an empty 204 No Content response to avoid unnecessary logs
        // and prevent session corruption from favicon requests
        return new Response('', Response::HTTP_NO_CONTENT, [
            'Content-Type' => 'image/x-icon',
            'Cache-Control' => 'public, max-age=604800', // Cache por 1 semana
        ]);
    }
}
