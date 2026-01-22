<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\TenantManager;
use Psr\Log\LoggerInterface;

final class DefaultController extends AbstractController
{
    private TenantManager $tenantManager;
    private LoggerInterface $logger;

    public function __construct(TenantManager $tenantManager, LoggerInterface $logger)
    {
        $this->tenantManager = $tenantManager;
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->render('default/index.html.twig', [
            'message' => 'Oops te equivocaste, checa la url',
        ]);
    }

    #[Route('/{dominio}', name: 'app_default')]
    public function index(string $dominio): Response
    {
        $this->logger->info("========== DEFAULT CONTROLLER: index() called ==========");
        $this->logger->info("[DefaultController] Dominio: {dominio}", ['dominio' => $dominio]);
        $this->logger->info("[DefaultController] Request URI: {uri}", ['uri' => $_SERVER['REQUEST_URI'] ?? 'N/A']);
        $this->logger->info("[DefaultController] Request Method: {method}", ['method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A']);

        $user = $this->getUser();

        if ($user) {
            $this->logger->info("[DefaultController] User authenticated", [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);
        } else {
            $this->logger->info("[DefaultController] No user authenticated");
        }

        if (!$user) {
            // Si no hay usuario, redirigir al login
            $this->logger->info("========== DEFAULT CONTROLLER: Redirecting to LOGIN ==========");
            return $this->redirectToRoute('app_login', ['dominio' => $dominio]);
        }

        // Usuario autenticado: redirigir al dashboard
        $dashboardUrl = $this->generateUrl('app_dashboard', ['dominio' => $dominio]);
        $this->logger->info("[DefaultController] Dashboard URL: {url}", ['url' => $dashboardUrl]);
        $this->logger->info("========== DEFAULT CONTROLLER: Redirecting to DASHBOARD ==========");

        return $this->redirectToRoute('app_dashboard', ['dominio' => $dominio]);
    }
}
