<?php

namespace App\EventListener;

use App\Entity\App\User;
use App\Service\UserActivityService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Psr\Log\LoggerInterface;

#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginActivityListener
{
    private UserActivityService $userActivityService;
    private RequestStack $requestStack;
    private LoggerInterface $logger;

    public function __construct(
        UserActivityService $userActivityService,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->userActivityService = $userActivityService;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        try {
            $user = $event->getUser();
            $request = $this->requestStack->getCurrentRequest();
            
            if ($user instanceof User && $request) {
                $this->userActivityService->logActivity(
                    $user,
                    'login',
                    'User logged in successfully',
                    $request
                );
                
                $this->logger->info('Login activity logged for user', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail()
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to log login activity', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
