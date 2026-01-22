<?php

namespace App\Command;

use App\Entity\App\User;
use App\Service\TenantManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-beneficiary-update',
    description: 'Prueba la actualización de beneficiarios para debugging',
)]
class TestBeneficiaryUpdateCommand extends Command
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
            ->addArgument('userId', InputArgument::REQUIRED, 'ID del usuario')
            ->addArgument('beneficiaryId', InputArgument::REQUIRED, 'ID del beneficiario')
            ->setHelp('Este comando simula una actualización de beneficiario para debugging');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenant = $input->getArgument('tenant');
        $userId = (int) $input->getArgument('userId');
        $beneficiaryId = (int) $input->getArgument('beneficiaryId');

        try {
            $io->title('Prueba de Actualización de Beneficiario');
            
            // Set tenant
            $this->tenantManager->setCurrentTenant($tenant);
            $io->success("Tenant configurado: {$tenant}");
            
            // Get entity manager
            $em = $this->tenantManager->getEntityManager();
            
            // Buscar el usuario
            $user = $em->getRepository(User::class)->findOneBy([
                'id' => $userId,
                'status' => \App\Enum\Status::ACTIVE,
            ]);
            
            if (!$user) {
                $io->error("Usuario no encontrado o inactivo: {$userId}");
                return Command::FAILURE;
            }
            
            $io->info("Usuario encontrado: {$user->getName()} (ID: {$user->getId()})");
            
            // Buscar el beneficiario
            $beneficiary = $user->getBeneficiaries()->filter(
                fn($b) => $b->getId() === $beneficiaryId && $b->getStatus() === \App\Enum\Status::ACTIVE
            )->first();
            
            if (!$beneficiary) {
                $io->error("Beneficiario no encontrado o inactivo: {$beneficiaryId}");
                return Command::FAILURE;
            }
            
            $io->info("Beneficiario encontrado: {$beneficiary->getName()} {$beneficiary->getLastName()}");
            $io->info("Foto actual: " . ($beneficiary->getPhoto() ?? 'Sin foto'));
            
            // Simular actualización de nombre
            $oldName = $beneficiary->getName();
            $newName = $oldName . ' [UPDATED]';
            
            $io->section('Simulando actualización...');
            
            $beneficiary->setName($newName);
            $beneficiary->setUpdatedAt(new \DateTimeImmutable());
            
            $em->flush();
            
            $io->success("Beneficiario actualizado exitosamente");
            $io->info("Nombre anterior: {$oldName}");
            $io->info("Nombre nuevo: {$newName}");
            
            // Verificar que se guardó
            $em->refresh($beneficiary);
            $io->info("Nombre en BD después del flush: {$beneficiary->getName()}");
            
        } catch (\Exception $e) {
            $io->error('Error durante la prueba: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
