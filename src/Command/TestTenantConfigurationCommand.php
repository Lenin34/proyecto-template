<?php

namespace App\Command;

use App\Service\TenantConfigurationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-tenant-configuration',
    description: 'Prueba la configuración de módulos de un tenant específico'
)]
class TestTenantConfigurationCommand extends Command
{
    public function __construct(
        private readonly TenantConfigurationService $tenantConfigurationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('dominio', InputArgument::REQUIRED, 'Dominio del tenant a probar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dominio = $input->getArgument('dominio');
        
        $io->title(sprintf('Probando configuración de módulos para tenant: %s', $dominio));
        
        try {
            $configuration = $this->tenantConfigurationService->getCurrentModules($dominio);
            
            $io->section('Configuración de módulos:');
            
            $tableData = [];
            foreach ($configuration as $module => $status) {
                $statusText = $status === '1' ? '✅ Habilitado' : '❌ Deshabilitado';
                $tableData[] = [$module, $status, $statusText];
            }
            
            $io->table(['Módulo', 'Valor', 'Estado'], $tableData);
            
            $io->section('JSON que recibiría React Native:');
            $io->text(json_encode($configuration, JSON_PRETTY_PRINT));
            
            // Contar módulos habilitados
            $enabledCount = count(array_filter($configuration, fn($status) => $status === '1'));
            $totalCount = count($configuration);
            
            $io->info(sprintf(
                'Resumen: %d de %d módulos habilitados (%d%%)',
                $enabledCount,
                $totalCount,
                round(($enabledCount / $totalCount) * 100)
            ));
            
        } catch (\Exception $e) {
            $io->error(sprintf('Error al obtener configuración: %s', $e->getMessage()));
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}
