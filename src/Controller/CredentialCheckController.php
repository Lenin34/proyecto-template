<?php

namespace App\Controller;

use App\Entity\App\User;
use App\Service\Auth\CredentialJwtService;
use App\Service\TenantManager;
use App\Service\TenantLogoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{dominio}/credential')]
final class CredentialCheckController extends AbstractController
{
    private TenantManager $tenantManager;
    private CredentialJwtService $credentialJwtService;
    private TenantLogoService $tenantLogoService;

    public function __construct(
        TenantManager $tenantManager,
        CredentialJwtService $credentialJwtService,
        TenantLogoService $tenantLogoService
    ) {
        $this->tenantManager = $tenantManager;
        $this->credentialJwtService = $credentialJwtService;
        $this->tenantLogoService = $tenantLogoService;
    }

    private array $themes = [
        'ts' => [
            'name' => 'Transformación Sindical',
            'bg_gradient' => 'linear-gradient(to bottom, #1C4488 0%, #08B5B1 45%, #4BBE7E 100%)',
            'card_bg' => 'rgba(255, 255, 255, 0.9)', // Blanco con transparencia
            'accent_color' => '#08B5B1',
            'text_color' => '#FFFFFF',
            'border_color' => 'transparent',
            'glass_effect' => false
        ],
        'issemym' => [
            'name' => 'ISSEMYM',
            'bg_gradient' => 'linear-gradient(135deg, #0f2a40 0%, #1A3A52 100%)',
            'card_bg' => 'rgba(255, 255, 255, 0.92)',
            'accent_color' => '#1A3A52', // Azul oscuro
            'text_color' => '#FFFFFF',
            'border_color' => 'rgba(255, 255, 255, 0.5)',
            'glass_effect' => true
        ],
        'rs' => [
            'name' => 'Red Sindical',
            'bg_gradient' => 'linear-gradient(135deg, #8B0000 0%, #CD5C5C 100%)', // Rojos
            'card_bg' => '#ffffff',
            'accent_color' => '#8B0000',
            'text_color' => '#FFFFFF',
            'border_color' => 'transparent',
            'glass_effect' => false
        ],
        'SNT' => [
            'name' => 'Sindicato Nacional',
            'bg_gradient' => 'linear-gradient(135deg, #4b6cb7 0%, #182848 100%)', // Azul marino
            'card_bg' => '#ffffff',
            'accent_color' => '#182848',
            'text_color' => '#FFFFFF',
            'border_color' => 'transparent',
            'glass_effect' => false
        ]
    ];

    #[Route('/check', name: 'app_credential_check', methods: ['GET'])]
    public function check(Request $request, string $dominio)
    {
        // Seleccionar tema o usar default (ts)
        $theme = $this->themes[$dominio] ?? $this->themes['ts'];
        
        // Obtener logo dinámicamente desde la configuración de BD
        $theme['logo'] = $this->tenantLogoService->getLogoUrl($dominio);

        try {
            $token = $request->query->get('token');
            if (!$token) {
                return $this->render('credential_check/dynamic_error.html.twig', [
                    'error' => 'Información no encontrada.',
                    'theme' => $theme
                ]);
            }

            $payload = $this->credentialJwtService->decodeCredentialToken($token);

            if (!$payload) {
                return $this->render('credential_check/dynamic_error.html.twig', [
                    'error' => 'La credencial ha vencido, vuelve a generar el código QR desde la app móvil.',
                    'theme' => $theme
                ]);
            }

            $userId = $payload['sub'] ?? null;

            $em = $this->tenantManager->getEntityManager();
            $user = $em->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\App\User', 'u')
                ->where('u.id = :id')
                ->setParameter('id', $userId)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$user) {
                return $this->render('credential_check/dynamic_error.html.twig', [
                    'error' => 'El usuario no pertenece a este tenant o no fue encontrado.',
                    'theme' => $theme
                ]);
            }

            return $this->render('credential_check/dynamic_index.html.twig', [
                'user' => $user,
                'theme' => $theme
            ]);

        } catch (\Throwable $e) {
            return $this->render('credential_check/dynamic_error.html.twig', [
                'error' => 'Error al verificar la credencial: ' . $e->getMessage(),
                'theme' => $theme
            ]);
        }
    }
}
