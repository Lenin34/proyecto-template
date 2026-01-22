<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestIdSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        // If there's an incoming header, keep it; otherwise generate
        $rid = $request->headers->get('X-Request-Id');
        if (!$rid) {
            try {
                $rid = bin2hex(random_bytes(8));
            } catch (\Throwable) {
                $rid = uniqid('req_', true);
            }
            $request->headers->set('X-Request-Id', $rid);
        }
        // Also expose in attributes for easy access
        if (!$request->attributes->has('request_id')) {
            $request->attributes->set('request_id', $rid);
        }
    }
}
