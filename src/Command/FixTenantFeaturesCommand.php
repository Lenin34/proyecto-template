<?php

namespace App\Command;

use App\Entity\Master\Tenant;
use App\Enum\Features;
use App\Service\TenantManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-tenant-features',
    description: 'Corrige la lógica de features de tenants para incluir todos los features con valores explícitos (1 o 0)'
)]
class FixTenantFeaturesCommand extends Command
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Corrigiendo lógica de features de tenants');
        
        try {
            // Conectar al dominio Master
            $this->tenantManager->setCurrentTenant('Master');
            $em = $this->tenantManager->getEntityManager();
            
            // Obtener todos los tenants
            $tenants = $em->getRepository(Tenant::class)->findAll();
            $allFeatures = Features::values();
            
            $io->info(sprintf('Encontrados %d tenants para procesar', count($tenants)));
            $io->info(sprintf('Features disponibles: %s', implode(', ', $allFeatures)));
            
            $updatedCount = 0;
            
            foreach ($tenants as $tenant) {
                $currentFeatures = $tenant->getFeatures();
                $needsUpdate = false;
                $newFeatures = [];
                
                // Procesar cada feature disponible
                foreach ($allFeatures as $feature) {
                    if (isset($currentFeatures[$feature])) {
                        // Si ya existe, mantener su valor actual
                        $newFeatures[$feature] = $currentFeatures[$feature];
                    } else {
                        // Si no existe, asignar '0' (deshabilitado)
                        $newFeatures[$feature] = '0';
                        $needsUpdate = true;
                    }
                }
                
                // Verificar si hay features obsoletos que debemos remover
                foreach ($currentFeatures as $feature => $value) {
                    if (!in_array($feature, $allFeatures)) {
                        $io->warning(sprintf('Tenant %s tiene feature obsoleto: %s', $tenant->getDominio(), $feature));
                        $needsUpdate = true;
                    }
                }
                
                if ($needsUpdate) {
                    $tenant->setFeatures($newFeatures);
                    $updatedCount++;
                    
                    $io->text(sprintf(
                        'Actualizando tenant: %s (features: %s)', 
                        $tenant->getDominio(),
                        json_encode($newFeatures)
                    ));
                }
            }
            
            if ($updatedCount > 0) {
                $em->flush();
                $io->success(sprintf('Se actualizaron %d tenants correctamente', $updatedCount));
            } else {
                $io->info('No se encontraron tenants que necesiten actualización');
            }
            
        } catch (\Exception $e) {
            $io->error(sprintf('Error al procesar tenants: %s', $e->getMessage()));
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}
