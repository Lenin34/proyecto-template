<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:dump-routes',
    description: 'Dump the application routes into public/assets/routes.json for client-side routing generation.'
)]
class DumpRoutesCommand extends Command
{
    private RouterInterface $router;
    private string $projectDir;

    public function __construct(RouterInterface $router, KernelInterface $kernel)
    {
        parent::__construct();
        $this->router = $router;
        $this->projectDir = $kernel->getProjectDir();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $collection = $this->router->getRouteCollection();
        $routes = [];

        foreach ($collection as $name => $route) {
            // Skip internal Symfony routes if any
            if (str_starts_with($name, '_')) {
                continue;
            }
            $path = $route->getPath();
            // Normalize Symfony-style placeholders {param} to :param for easier replacement
            $normalized = preg_replace('/\{([^}]+)\}/', ':$1', $path);
            $routes[$name] = $normalized;
        }

        ksort($routes);

        $filesystem = new Filesystem();
        $assetsDir = $this->projectDir . '/public/assets';
        if (!$filesystem->exists($assetsDir)) {
            $filesystem->mkdir($assetsDir, 0775);
        }
        $target = $assetsDir . '/routes.json';

        $filesystem->dumpFile($target, json_encode($routes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $io->success(sprintf('Exported %d routes to %s', count($routes), $target));
        return Command::SUCCESS;
    }
}
