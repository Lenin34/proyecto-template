<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Optional helper to ensure routes.json exists after first HTTP request in dev.
 * It runs app:dump-routes once per process if routes.json is missing.
 */
class RoutesDumpOnWarmupSubscriber implements EventSubscriberInterface
{
    private KernelInterface $kernel;
    private bool $attempted = false;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if ($this->attempted) {
            return;
        }
        $this->attempted = true;

        $projectDir = $this->kernel->getProjectDir();
        $target = $projectDir . '/public/assets/routes.json';
        if (is_file($target)) {
            return; // already present
        }

        // Run the command programmatically (best-effort)
        try {
            $application = new Application($this->kernel);
            $application->setAutoExit(false);
            $input = new ArrayInput(['command' => 'app:dump-routes']);
            $output = new BufferedOutput();
            $application->run($input, $output);
            // You can log $output->fetch() if needed
        } catch (\Throwable $e) {
            // Fail silently; developer can run bin/console app:dump-routes
        }
    }
}
