<?php

namespace App\EventSubscriber;

use App\Service\ErrorHandlerService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;

class TenantExceptionSubscriber implements EventSubscriberInterface
{
    private ErrorHandlerService $errorHandler;

    public function __construct(ErrorHandlerService $errorHandler)
    {
        $this->errorHandler = $errorHandler;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 1]
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();
        
        // Obtener el tenant de la ruta si está disponible
        $tenant = $request->attributes->get('dominio');

        $response = null;

        // Excepciones de tenant inválido
        if ($exception instanceof \RuntimeException && 
            str_contains($exception->getMessage(), 'Invalid tenant')) {
            $response = $this->errorHandler->handleTenantError($exception, $tenant);
        }
        // Excepciones de JWT
        elseif ($exception instanceof JWTDecodeFailureException) {
            $response = $this->errorHandler->handleTokenError($exception);
        }
        // Excepciones de autenticación
        elseif ($exception instanceof AuthenticationException) {
            $response = $this->errorHandler->handleTokenError($exception);
        }

        if ($response) {
            $event->setResponse($response);
        }
    }
} 