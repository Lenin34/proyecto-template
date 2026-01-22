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
    name: 'app:check-beneficiary-photos',
    description: 'Verifica las fotos de beneficiarios en la base de datos',
)]
class CheckBeneficiaryPhotosCommand extends Command
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
            ->setHelp('Este comando verifica las fotos de beneficiarios en la base de datos para un tenant específico');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenant = $input->getArgument('tenant');

        try {
            $io->title('Verificación de Fotos de Beneficiarios');
            
            // Set tenant
            $this->tenantManager->setCurrentTenant($tenant);
            $io->success("Tenant configurado: {$tenant}");
            
            // Get entity manager
            $em = $this->tenantManager->getEntityManager();
            
            // Buscar todos los beneficiarios
            $beneficiaries = $em->createQuery(
                'SELECT b, u FROM App\Entity\Beneficiary b
                 JOIN b.user u
                 WHERE b.status = :status
                 ORDER BY b.updated_at DESC'
            )
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->setMaxResults(10) // Limitar a los últimos 10
            ->getResult();

            $io->section('📸 Beneficiarios encontrados:');
            
            if (empty($beneficiaries)) {
                $io->warning('No se encontraron beneficiarios en la base de datos');
                return Command::SUCCESS;
            }

            $io->info(sprintf('Encontrados %d beneficiarios en la base de datos', count($beneficiaries)));

            foreach ($beneficiaries as $beneficiary) {
                $user = $beneficiary->getUser();
                $photoPath = $beneficiary->getPhoto();

                $io->writeln('');
                $io->writeln("🔍 <info>Beneficiario ID:</info> {$beneficiary->getId()}");
                $io->writeln("👤 <info>Nombre:</info> {$beneficiary->getName()} {$beneficiary->getLastName()}");
                $io->writeln("👥 <info>Usuario:</info> {$user->getName()} (ID: {$user->getId()})");
                $io->writeln("📅 <info>Actualizado:</info> " . ($beneficiary->getUpdatedAt() ? $beneficiary->getUpdatedAt()->format('Y-m-d H:i:s') : 'N/A'));
                
                if ($photoPath) {
                    $io->writeln("📸 <info>Ruta de foto:</info> {$photoPath}");
                    
                    // Verificar si el archivo físico existe
                    $uploadsDir = '/var/www/html/public/uploads';
                    $fullPath = $uploadsDir . '/' . $photoPath;
                    $exists = file_exists($fullPath);
                    $io->writeln("📁 <info>Archivo físico:</info> " . ($exists ? '✅ Existe' : '❌ No existe'));
                    $io->writeln("📍 <info>Ruta completa:</info> {$fullPath}");
                    
                    if ($exists) {
                        $fileSize = filesize($fullPath);
                        $mimeType = mime_content_type($fullPath);
                        $io->writeln("📊 <info>Tamaño:</info> " . number_format($fileSize / 1024, 2) . " KB");
                        $io->writeln("🏷️ <info>Tipo MIME:</info> {$mimeType}");
                    }
                } else {
                    $io->writeln("📸 <info>Foto:</info> ❌ Sin foto");
                }
                
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
