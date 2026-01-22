<?php

namespace App\Command;

use App\Service\TenantLogoService;
use App\Service\TenantManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[AsCommand(
    name: 'app:tenant-logo',
    description: 'Gestiona logos de tenants (listar, info, eliminar)',
)]
class TenantLogoCommand extends Command
{
    public function __construct(
        private readonly TenantLogoService $tenantLogoService,
        private readonly TenantManager $tenantManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Acción a realizar: info, delete, list')
            ->addArgument('tenant', InputArgument::OPTIONAL, 'Dominio del tenant')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Aplicar a todos los tenants (para list)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $tenant = $input->getArgument('tenant');

        switch ($action) {
            case 'info':
                return $this->showTenantLogoInfo($io, $tenant);
            
            case 'delete':
                return $this->deleteTenantLogo($io, $tenant);
            
            case 'list':
                return $this->listTenantLogos($io, $input->getOption('all'));
            
            default:
                $io->error("Acción no válida: {$action}. Acciones disponibles: info, delete, list");
                return Command::FAILURE;
        }
    }

    private function showTenantLogoInfo(SymfonyStyle $io, ?string $tenant): int
    {
        if (!$tenant) {
            $io->error('Debes especificar un tenant para la acción info');
            return Command::FAILURE;
        }

        $io->title("Información del Logo - Tenant: {$tenant}");

        try {
            $logoInfo = $this->tenantLogoService->getTenantLogoInfo($tenant);

            $io->definitionList(
                ['Tenant Domain' => $logoInfo['tenant_domain']],
                ['Logo Path' => $logoInfo['logo_path'] ?? 'N/A'],
                ['Logo URL' => $logoInfo['logo_url']],
                ['Is Default' => $logoInfo['is_default'] ? 'Sí' : 'No'],
                ['Is Legacy' => $logoInfo['is_legacy'] ? 'Sí' : 'No'],
                ['File Exists' => $logoInfo['exists'] ? 'Sí' : 'No']
            );

            if (isset($logoInfo['error'])) {
                $io->warning("Error: {$logoInfo['error']}");
            }

            $io->success('Información obtenida exitosamente');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Error al obtener información: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function deleteTenantLogo(SymfonyStyle $io, ?string $tenant): int
    {
        if (!$tenant) {
            $io->error('Debes especificar un tenant para la acción delete');
            return Command::FAILURE;
        }

        $io->title("Eliminar Logo - Tenant: {$tenant}");

        // Mostrar información actual
        $logoInfo = $this->tenantLogoService->getTenantLogoInfo($tenant);
        
        if ($logoInfo['is_default']) {
            $io->warning('Este tenant ya está usando el logo por defecto');
            return Command::SUCCESS;
        }

        $io->text("Logo actual: {$logoInfo['logo_url']}");

        if (!$io->confirm('¿Estás seguro de que quieres eliminar este logo?', false)) {
            $io->info('Operación cancelada');
            return Command::SUCCESS;
        }

        try {
            $success = $this->tenantLogoService->deleteTenantLogo($tenant);

            if ($success) {
                $io->success('Logo eliminado exitosamente');
                return Command::SUCCESS;
            } else {
                $io->error('Error al eliminar el logo');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error("Error al eliminar logo: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function listTenantLogos(SymfonyStyle $io, bool $all): int
    {
        $io->title('Lista de Logos de Tenants');

        try {
            // Obtener lista de tenants
            $tenants = $this->getAllTenants();

            if (empty($tenants)) {
                $io->warning('No se encontraron tenants');
                return Command::SUCCESS;
            }

            $tableData = [];

            foreach ($tenants as $tenantDomain) {
                $logoInfo = $this->tenantLogoService->getTenantLogoInfo($tenantDomain);
                
                $status = '';
                if ($logoInfo['is_default']) {
                    $status = '🔘 Default';
                } elseif ($logoInfo['is_legacy']) {
                    $status = '🔶 Legacy';
                } elseif ($logoInfo['exists']) {
                    $status = '✅ Custom';
                } else {
                    $status = '❌ Missing';
                }

                $tableData[] = [
                    $tenantDomain,
                    $status,
                    $logoInfo['logo_path'] ?? 'N/A',
                    $logoInfo['exists'] ? 'Sí' : 'No'
                ];
            }

            $io->table(
                ['Tenant', 'Estado', 'Ruta', 'Existe'],
                $tableData
            );

            $io->success('Lista generada exitosamente');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Error al listar logos: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function getAllTenants(): array
    {
        try {
            $allowedTenants = $this->tenantManager->getAllowedTenants();
            return array_keys($allowedTenants);

        } catch (\Exception $e) {
            return ['ts', 'rs']; // Fallback a tenants conocidos
        }
    }
}
