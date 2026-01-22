<?php

namespace App\Command;

use App\Entity\Master\MasterUser;
use App\Enum\Status;
use App\Service\TenantManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:master-user',
    description: 'Gestiona usuarios administrativos del tenant Master'
)]
class MasterUserCommand extends Command
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Acción a realizar: create, list, delete, change-password, add-role, remove-role')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Email del usuario')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Nombre del usuario')
            ->addOption('last-name', null, InputOption::VALUE_OPTIONAL, 'Apellido del usuario')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Contraseña del usuario')
            ->addOption('role', null, InputOption::VALUE_OPTIONAL, 'Rol a agregar/remover (ROLE_MASTER_ADMIN, ROLE_MASTER_USER)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forzar la acción sin confirmación');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        // Configurar el tenant Master
        $this->tenantManager->setCurrentTenant('Master');
        $em = $this->tenantManager->getEntityManager();

        switch ($action) {
            case 'create':
                return $this->createUser($io, $input, $em);
            
            case 'list':
                return $this->listUsers($io, $em);
            
            case 'delete':
                return $this->deleteUser($io, $input, $em);
            
            case 'change-password':
                return $this->changePassword($io, $input, $em);
            
            case 'add-role':
                return $this->addRole($io, $input, $em);
            
            case 'remove-role':
                return $this->removeRole($io, $input, $em);
            
            default:
                $io->error("Acción no válida: {$action}");
                $io->info('Acciones disponibles: create, list, delete, change-password, add-role, remove-role');
                return Command::FAILURE;
        }
    }

    private function createUser(SymfonyStyle $io, InputInterface $input, $em): int
    {
        $io->title('Crear Usuario Master');

        // Obtener datos del usuario
        $email = $input->getOption('email');
        if (!$email) {
            $email = $io->ask('Email del usuario');
        }

        // Verificar si el usuario ya existe
        $existingUser = $em->getRepository(MasterUser::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error("Ya existe un usuario con el email: {$email}");
            return Command::FAILURE;
        }

        $name = $input->getOption('name');
        if (!$name) {
            $name = $io->ask('Nombre del usuario');
        }

        $lastName = $input->getOption('last-name');
        if (!$lastName) {
            $lastName = $io->ask('Apellido del usuario (opcional)', '');
        }

        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Contraseña del usuario');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $io->askQuestion($question);
        }

        // Preguntar por el rol
        $roleChoice = $io->choice(
            '¿Qué rol deseas asignar?',
            ['ROLE_MASTER_ADMIN' => 'Administrador Master (acceso completo)', 'ROLE_MASTER_USER' => 'Usuario Master (solo lectura)'],
            'ROLE_MASTER_ADMIN'
        );

        // Crear el usuario
        $user = new MasterUser();
        $user->setEmail($email);
        $user->setName($name);
        $user->setLastName($lastName);
        $user->setStatus(Status::ACTIVE);
        $user->setRoles([$roleChoice]);
        $user->setCreatedAt(new \DateTime());
        $user->setUpdatedAt(new \DateTime());

        // Hash de la contraseña
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        $io->success("Usuario Master creado exitosamente!");
        $io->table(
            ['Campo', 'Valor'],
            [
                ['ID', $user->getId()],
                ['Email', $user->getEmail()],
                ['Nombre', $user->getFullName()],
                ['Roles', implode(', ', $user->getRoles())],
                ['Estado', $user->getStatus()->value],
            ]
        );

        return Command::SUCCESS;
    }

    private function listUsers(SymfonyStyle $io, $em): int
    {
        $io->title('Usuarios Master');

        $users = $em->getRepository(MasterUser::class)->findAll();

        if (empty($users)) {
            $io->warning('No hay usuarios Master registrados.');
            return Command::SUCCESS;
        }

        $tableData = [];
        foreach ($users as $user) {
            $tableData[] = [
                $user->getId(),
                $user->getEmail(),
                $user->getFullName(),
                implode(', ', $user->getRoles()),
                $user->getStatus()->value,
                $user->getLastLogin() ? $user->getLastLogin()->format('Y-m-d H:i:s') : 'Nunca',
                $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        $io->table(
            ['ID', 'Email', 'Nombre', 'Roles', 'Estado', 'Último Login', 'Creado'],
            $tableData
        );

        return Command::SUCCESS;
    }

    private function deleteUser(SymfonyStyle $io, InputInterface $input, $em): int
    {
        $io->title('Eliminar Usuario Master');

        $email = $input->getOption('email');
        if (!$email) {
            $email = $io->ask('Email del usuario a eliminar');
        }

        $user = $em->getRepository(MasterUser::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error("No se encontró un usuario con el email: {$email}");
            return Command::FAILURE;
        }

        $io->warning("Estás a punto de eliminar el usuario: {$user->getFullName()} ({$user->getEmail()})");
        
        if (!$input->getOption('force')) {
            if (!$io->confirm('¿Estás seguro?', false)) {
                $io->info('Operación cancelada.');
                return Command::SUCCESS;
            }
        }

        $em->remove($user);
        $em->flush();

        $io->success("Usuario eliminado exitosamente.");

        return Command::SUCCESS;
    }

    private function changePassword(SymfonyStyle $io, InputInterface $input, $em): int
    {
        $io->title('Cambiar Contraseña de Usuario Master');

        $email = $input->getOption('email');
        if (!$email) {
            $email = $io->ask('Email del usuario');
        }

        $user = $em->getRepository(MasterUser::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error("No se encontró un usuario con el email: {$email}");
            return Command::FAILURE;
        }

        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Nueva contraseña');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $io->askQuestion($question);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $user->setUpdatedAt(new \DateTime());

        $em->flush();

        $io->success("Contraseña actualizada exitosamente para: {$user->getEmail()}");

        return Command::SUCCESS;
    }

    private function addRole(SymfonyStyle $io, InputInterface $input, $em): int
    {
        $io->title('Agregar Rol a Usuario Master');

        $email = $input->getOption('email');
        if (!$email) {
            $email = $io->ask('Email del usuario');
        }

        $user = $em->getRepository(MasterUser::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error("No se encontró un usuario con el email: {$email}");
            return Command::FAILURE;
        }

        $role = $input->getOption('role');
        if (!$role) {
            $role = $io->choice('Rol a agregar', ['ROLE_MASTER_ADMIN', 'ROLE_MASTER_USER']);
        }

        if ($user->hasRole($role)) {
            $io->warning("El usuario ya tiene el rol: {$role}");
            return Command::SUCCESS;
        }

        $user->addRole($role);
        $user->setUpdatedAt(new \DateTime());
        $em->flush();

        $io->success("Rol {$role} agregado exitosamente a: {$user->getEmail()}");
        $io->info('Roles actuales: ' . implode(', ', $user->getRoles()));

        return Command::SUCCESS;
    }

    private function removeRole(SymfonyStyle $io, InputInterface $input, $em): int
    {
        $io->title('Remover Rol de Usuario Master');

        $email = $input->getOption('email');
        if (!$email) {
            $email = $io->ask('Email del usuario');
        }

        $user = $em->getRepository(MasterUser::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error("No se encontró un usuario con el email: {$email}");
            return Command::FAILURE;
        }

        $role = $input->getOption('role');
        if (!$role) {
            $currentRoles = array_filter($user->getRoles(), fn($r) => $r !== 'ROLE_MASTER_USER');
            if (empty($currentRoles)) {
                $io->warning('El usuario solo tiene el rol base ROLE_MASTER_USER que no se puede remover.');
                return Command::SUCCESS;
            }
            $role = $io->choice('Rol a remover', $currentRoles);
        }

        if (!$user->hasRole($role)) {
            $io->warning("El usuario no tiene el rol: {$role}");
            return Command::SUCCESS;
        }

        $user->removeRole($role);
        $user->setUpdatedAt(new \DateTime());
        $em->flush();

        $io->success("Rol {$role} removido exitosamente de: {$user->getEmail()}");
        $io->info('Roles actuales: ' . implode(', ', $user->getRoles()));

        return Command::SUCCESS;
    }
}

