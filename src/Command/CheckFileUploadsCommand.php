<?php

namespace App\Command;

use App\Service\TenantManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-file-uploads',
    description: 'Verifica los archivos subidos en la base de datos',
)]
class CheckFileUploadsCommand extends Command
{
    private TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'Nombre del tenant')
            ->setHelp('Este comando verifica los archivos subidos en la base de datos para un tenant específico');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenant = $input->getArgument('tenant');

        try {
            $io->title('Verificación de Archivos Subidos');
            
            // Set tenant
            $this->tenantManager->setCurrentTenant($tenant);
            $io->success("Tenant configurado: {$tenant}");
            
            // Get entity manager
            $em = $this->tenantManager->getEntityManager();
            
            // Buscar todos los FormEntryValue que son de tipo file
            $fileEntryValues = $em->createQuery(
                'SELECT fev, fe, ft, ftf FROM App\Entity\FormEntryValue fev 
                 JOIN fev.formEntry fe 
                 JOIN fe.formTemplate ft 
                 JOIN fev.formTemplateField ftf 
                 WHERE ftf.type = :fileType 
                 AND fev.status = :status
                 ORDER BY fev.id DESC'
            )
            ->setParameter('fileType', 'file')
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->setMaxResults(20) // Limitar a los últimos 20
            ->getResult();

            $io->section('📁 Archivos encontrados en la base de datos:');
            
            if (empty($fileEntryValues)) {
                $io->warning('No se encontraron archivos en la base de datos');
                return Command::SUCCESS;
            }

            $io->info(sprintf('Encontrados %d archivos en la base de datos', count($fileEntryValues)));

            foreach ($fileEntryValues as $entryValue) {
                $value = $entryValue->getValue();
                $field = $entryValue->getFormTemplateField();
                $formTemplate = $entryValue->getFormEntry()->getFormTemplate();
                $user = $entryValue->getFormEntry()->getUser();

                $io->writeln('');
                $io->writeln("🔍 <info>Archivo ID:</info> {$entryValue->getId()}");
                $io->writeln("📋 <info>Formulario:</info> {$formTemplate->getName()} (ID: {$formTemplate->getId()})");
                $io->writeln("👤 <info>Usuario:</info> {$user->getName()} (ID: {$user->getId()})");
                $io->writeln("🏷️ <info>Campo:</info> {$field->getLabel()} (ID: {$field->getId()})");
                
                // Intentar decodificar el valor como JSON
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $io->writeln("📄 <info>Valor (JSON):</info>");
                    foreach ($decoded as $key => $val) {
                        $io->writeln("   - {$key}: {$val}");
                    }
                    
                    // Verificar si el archivo físico existe
                    if (isset($decoded['file_path'])) {
                        // Usar ruta hardcodeada para uploads (ajustar según tu configuración)
                        $uploadsDir = '/var/www/html/public/uploads';
                        $fullPath = $uploadsDir . $decoded['file_path'];
                        $exists = file_exists($fullPath);
                        $io->writeln("📁 <info>Archivo físico:</info> " . ($exists ? '✅ Existe' : '❌ No existe'));
                        $io->writeln("📍 <info>Ruta completa:</info> {$fullPath}");
                    }
                } else {
                    $io->writeln("📄 <info>Valor (texto):</info> {$value}");
                }
                
                $io->writeln("⏰ <info>Creado:</info> " . $entryValue->getFormEntry()->getCreatedAt()->format('Y-m-d H:i:s'));
                $io->writeln('---');
            }

            $io->success('Verificación completada');
            
        } catch (\Exception $e) {
            $io->error('Error durante la verificación: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
