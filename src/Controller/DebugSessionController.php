<?php

namespace App\Controller;

use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DebugSessionController extends AbstractController
{
    #[Route('/{dominio}/debug-session', name: 'app_debug_session')]
    public function index(Request $request, TenantManager $tenantManager)
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        
        return new JsonResponse([
            'tenant_manager_current' => $tenantManager->getCurrentTenant(),
            'tenant_from_route' => $request->attributes->get('dominio'),
            'session_id' => $session ? $session->getId() : 'no_session',
            'session_name' => $session ? $session->getName() : 'none',
            'session_started' => $session ? $session->isStarted() : false,
            'session_data' => $session ? $session->all() : [],
            'cookies' => $request->cookies->all(),
            'headers' => [
                'host' => $request->headers->get('host'),
                'x-forwarded-proto' => $request->headers->get('x-forwarded-proto'),
                'x-forwarded-for' => $request->headers->get('x-forwarded-for'),
                'user-agent' => $request->headers->get('user-agent'),
                'cookie' => $request->headers->get('cookie'), // Check raw cookie header
            ],
            'is_secure' => $request->isSecure(),
            'user' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
        ]);
    }
}
