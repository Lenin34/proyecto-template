<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\TenantManager;

/**
 * Controller for the error logs visualization page
 */
class ErrorLogViewController extends AbstractController
{
    private TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    /**
     * Renders the error logs visualization page
     */
    #[Route("/{dominio}/admin/logs", name: "app_error_logs")]
    public function index(string $dominio): Response
    {
        
        return $this->render('error_logs/index.html.twig');
    }
}