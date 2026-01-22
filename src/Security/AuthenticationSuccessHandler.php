<?php
namespace App\Security;

use App\Entity\App\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

class AuthenticationSuccessHandler
{
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();
        
        if (!$user instanceof User) {
            return;
        }

        $data['user_id'] = $user->getId();
        $data['company_id'] = $user->getCompany()?->getId();

        $event->setData($data);
    }
}