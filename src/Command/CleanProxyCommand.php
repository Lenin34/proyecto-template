<?php

namespace App\Command;

use App\Entity\App\Company;
use App\Entity\App\User;
use App\Service\EntityProxyCleanerService;
use App\Service\TenantManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-proxy',
    description: 'Limpia problemas de proxy en entidades para un tenant específico',
)]
class CleanProxyCommand extends Command
{
    private TenantManager $tenantManager;
    private EntityProxyCleanerService $proxyCleanerService;

    public function __construct(
        TenantManager $tenantManager,
        EntityProxyCleanerService $proxyCleanerService
    ) {
        $this->tenantManager = $tenantManager;
        $this->proxyCleanerService = $proxyCleanerService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'Tenant a limpiar (ts, SNT)')
            ->setHelp('Este comando limpia problemas de proxy en las entidades de un tenant específico.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenant = $input->getArgument('tenant');

        if (!$this->tenantManager->isValidTenant($tenant)) {
            $io->error(sprintf('Tenant "%s" no es válido. Los tenants permitidos son: %s', 
                $tenant, 
                implode(', ', $this->tenantManager->getAllowedTenants())
            ));
            return Command::FAILURE;
        }

        $io->title(sprintf('Limpiando problemas de proxy para el tenant: %s', $tenant));

        try {
            // Establecer el tenant actual
            $this->tenantManager->setCurrentTenant($tenant);
            $em = $this->tenantManager->getEntityManager();

            $io->section('Analizando usuarios con problemas de proxy...');

            // Obtener todos los usuarios
            $users = $em->getRepository(User::class)->findAll();
            $problematicUsers = [];
            $cleanedUsers = 0;

            foreach ($users as $user) {
                $hasProblems = false;
                $problems = [];

                // Verificar problemas con Company
                if ($user->getCompany() !== null) {
                    try {
                        $company = $user->getCompany();
                        
                        if ($this->proxyCleanerService->isUninitializedProxy($company)) {
                            $problems[] = 'Company proxy no inicializado';
                            $hasProblems = true;
                        } else {
                            // Verificar si la compañía existe en la base de datos actual
                            $companyId = $company->getId();
                            if (!$this->proxyCleanerService->entityExistsInCurrentDatabase($em, Company::class, $companyId)) {
                                $problems[] = 'Company referencia huérfana';
                                $hasProblems = true;
                            }
                        }
                    } catch (\Exception $e) {
                        $problems[] = 'Error accediendo a Company: ' . $e->getMessage();
                        $hasProblems = true;
                    }
                }

                if ($hasProblems) {
                    $problematicUsers[] = [
                        'user' => $user,
                        'problems' => $problems
                    ];
                }
            }

            if (empty($problematicUsers)) {
                $io->success('No se encontraron usuarios con problemas de proxy.');
                return Command::SUCCESS;
            }

            $io->warning(sprintf('Se encontraron %d usuarios con problemas de proxy.', count($problematicUsers)));

            // Mostrar problemas encontrados
            foreach ($problematicUsers as $problematicUser) {
                $user = $problematicUser['user'];
                $problems = $problematicUser['problems'];
                
                $io->writeln(sprintf('Usuario ID %d (%s): %s', 
                    $user->getId(), 
                    $user->getEmail() ?? 'Sin email',
                    implode(', ', $problems)
                ));
            }

            if ($io->confirm('¿Desea limpiar estos problemas?', true)) {
                $io->section('Limpiando problemas de proxy...');

                foreach ($problematicUsers as $problematicUser) {
                    $user = $problematicUser['user'];
                    
                    try {
                        // Limpiar referencias problemáticas
                        $cleanedUser = $this->proxyCleanerService->cleanAllProxyReferences($user, $em);
                        $em->persist($cleanedUser);
                        $cleanedUsers++;
                        
                        $io->writeln(sprintf('✓ Usuario ID %d limpiado', $user->getId()));
                    } catch (\Exception $e) {
                        $io->writeln(sprintf('✗ Error limpiando usuario ID %d: %s', $user->getId(), $e->getMessage()));
                    }
                }

                // Guardar cambios
                $em->flush();
                
                $io->success(sprintf('Se limpiaron %d usuarios exitosamente.', $cleanedUsers));
            } else {
                $io->info('Operación cancelada por el usuario.');
            }

        } catch (\Exception $e) {
            $io->error(sprintf('Error durante la limpieza: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
