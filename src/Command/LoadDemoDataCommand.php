<?php

namespace App\Command;

use App\Entity\App\Beneficiary;
use App\Entity\App\Company;
use App\Entity\App\FormEntry;
use App\Entity\App\FormEntryValue;
use App\Entity\App\FormTemplate;
use App\Entity\App\FormTemplateField;
use App\Entity\App\Region;
use App\Entity\App\Role;
use App\Entity\App\User;
use App\Enum\Status;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:load-demo-data', description: 'Carga datos de demostración completos para la aplicación')]
class LoadDemoDataCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private ManagerRegistry $doctrine;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        ManagerRegistry $doctrine,
        UserPasswordHasherInterface $passwordHasher
    )
    {
        parent::__construct();
        $this->doctrine = $doctrine;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->addOption('tenant', 't', InputOption::VALUE_REQUIRED, 'Tenant para cargar los datos', 'ts')
            ->addOption('clear', 'c', InputOption::VALUE_NONE, 'Limpiar datos existentes antes de cargar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenant = $input->getOption('tenant');
        $clear = $input->getOption('clear');

        $io->title('Cargando datos de demostración');
        $io->info("Tenant: {$tenant}");

        try {
            // Configurar EntityManager para el tenant específico
            $this->entityManager = $this->doctrine->getManager($tenant);

            if ($clear) {
                $io->section('Limpiando datos existentes...');
                $this->clearExistingData($io);
            }

            $io->section('Creando roles...');
            $roles = $this->createRoles($io);

            $io->section('Creando regiones...');
            $regions = $this->createRegions($io);

            $io->section('Creando empresas...');
            $companies = $this->createCompanies($io, $regions);

            $io->section('Creando usuarios...');
            $users = $this->createUsers($io, $roles, $companies, $regions);

            $io->section('Creando beneficiarios...');
            $this->createBeneficiaries($io, $users);

            $io->section('Creando plantillas de formularios...');
            $formTemplates = $this->createFormTemplates($io);

            $io->section('Creando respuestas de formularios...');
            $this->createFormEntries($io, $formTemplates, $users);

            $io->success('¡Datos de demostración cargados exitosamente!');
            $io->table(['Entidad', 'Cantidad'], [
                ['Roles', count($roles)],
                ['Regiones', count($regions)],
                ['Empresas', count($companies)],
                ['Usuarios', count($users)],
                ['Plantillas de formularios', count($formTemplates)],
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error al cargar datos de demostración: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function clearExistingData(SymfonyStyle $io): void
    {
        $connection = $this->entityManager->getConnection();

        // Desactivar verificación de claves foráneas temporalmente
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        // Limpiar tablas en orden
        $tables = [
            'form_entry_value',
            'form_entry',
            'form_template_field',
            'form_template',
            'beneficiary',
            'region_user',
            'user',
            'company',
            'region',
            'role'
        ];

        foreach ($tables as $table) {
            try {
                $connection->executeStatement("DELETE FROM {$table}");
                $io->text("Limpiada tabla: {$table}");
            } catch (\Exception $e) {
                $io->warning("No se pudo limpiar la tabla {$table}: " . $e->getMessage());
            }
        }

        // Reactivar verificación de claves foráneas
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $io->success('Datos existentes eliminados');
    }

    private function createRoles(SymfonyStyle $io): array
    {
        $rolesData = [
            'Administrador',
            'Supervisor',
            'Empleado',
            'Recursos Humanos',
            'Gerente'
        ];

        $roles = [];
        $roleRepository = $this->entityManager->getRepository(Role::class);

        foreach ($rolesData as $roleName) {
            // Verificar si el rol ya existe
            $existingRole = $roleRepository->findOneBy(['name' => $roleName]);
            if ($existingRole) {
                $roles[] = $existingRole;
                $io->text("Rol ya existe: {$roleName}");
                continue;
            }

            $role = new Role();
            $role->setName($roleName);
            $role->setCreatedAt(new \DateTimeImmutable());
            $role->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($role);
            $roles[] = $role;
            $io->text("Creado rol: {$roleName}");
        }

        $this->entityManager->flush();
        return $roles;
    }

    private function createRegions(SymfonyStyle $io): array
    {
        $regionsData = [
            'Norte',
            'Sur',
            'Centro',
            'Occidente',
            'Oriente',
            'Metropolitana'
        ];

        $regions = [];
        $regionRepository = $this->entityManager->getRepository(Region::class);

        foreach ($regionsData as $regionName) {
            // Verificar si la región ya existe
            $existingRegion = $regionRepository->findOneBy(['name' => $regionName]);
            if ($existingRegion) {
                $regions[] = $existingRegion;
                $io->text("Región ya existe: {$regionName}");
                continue;
            }

            $region = new Region();
            $region->setName($regionName);
            $region->setStatus(Status::ACTIVE);
            $region->setCreatedAt(new \DateTimeImmutable());
            $region->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($region);
            $regions[] = $region;
            $io->text("Creada región: {$regionName}");
        }

        $this->entityManager->flush();
        return $regions;
    }

    private function createCompanies(SymfonyStyle $io, array $regions): array
    {
        $companiesData = [
            'Tecnología Avanzada S.A.',
            'Servicios Corporativos del Norte',
            'Industrias del Centro',
            'Comercializadora del Sur',
            'Logística Integral',
            'Consultoría Empresarial',
            'Desarrollo de Software',
            'Manufactura Especializada'
        ];

        $companies = [];
        $regionRepository = $this->entityManager->getRepository(Region::class);

        foreach ($companiesData as $index => $companyName) {
            // Obtener la región directamente del repositorio
            $regionIndex = $index % count($regions);
            $regionName = $regions[$regionIndex]->getName();
            $region = $regionRepository->findOneBy(['name' => $regionName]);

            $company = new Company();
            $company->setName($companyName);
            $company->setStatus(Status::ACTIVE);
            $company->setRegion($region);
            $company->setCreatedAt(new \DateTimeImmutable());
            $company->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($company);
            $companies[] = $company;
            $io->text("Creada empresa: {$companyName}");
        }

        $this->entityManager->flush();
        return $companies;
    }

    private function createUsers(SymfonyStyle $io, array $roles, array $companies, array $regions): array
    {
        $usersData = [
            [
                'name' => 'Juan Carlos',
                'last_name' => 'García López',
                'email' => 'juan.garcia@demo.com',
                'curp' => 'GALJ850315HDFRPN01',
                'phone_number' => '5551234567',
                'employee_number' => 'EMP001',
                'gender' => 'Masculino',
                'education' => 'Licenciatura'
            ],
            [
                'name' => 'María Elena',
                'last_name' => 'Rodríguez Martínez',
                'email' => 'maria.rodriguez@demo.com',
                'curp' => 'ROMM900422MDFDRR08',
                'phone_number' => '5551234568',
                'employee_number' => 'EMP002',
                'gender' => 'Femenino',
                'education' => 'Maestría'
            ],
            [
                'name' => 'Carlos Alberto',
                'last_name' => 'Hernández Silva',
                'email' => 'carlos.hernandez@demo.com',
                'curp' => 'HESC780912HDFRRL03',
                'phone_number' => '5551234569',
                'employee_number' => 'EMP003',
                'gender' => 'Masculino',
                'education' => 'Ingeniería'
            ],
            [
                'name' => 'Ana Patricia',
                'last_name' => 'López Fernández',
                'email' => 'ana.lopez@demo.com',
                'curp' => 'LOFA920618MDFPRN05',
                'phone_number' => '5551234570',
                'employee_number' => 'EMP004',
                'gender' => 'Femenino',
                'education' => 'Licenciatura'
            ],
            [
                'name' => 'Ximena',
                'last_name' => 'Hernández',
                'email' => 'xhr271003@gmail.com',
                'curp' => 'HERX031027MQTRDMA1',
                'phone_number' => '5551234571',
                'employee_number' => 'EMP005',
                'gender' => 'Femenino',
                'education' => 'Licenciatura'
            ]
        ];

        $users = [];
        foreach ($usersData as $index => $userData) {
            $user = new User();
            $user->setName($userData['name']);
            $user->setLastName($userData['last_name']);
            $user->setEmail($userData['email']);
            $user->setCurp($userData['curp']);
            $user->setPhoneNumber($userData['phone_number']);
            $user->setEmployeeNumber($userData['employee_number']);
            $user->setGender($userData['gender']);
            $user->setEducation($userData['education']);
            $user->setStatus(Status::ACTIVE);
            $user->setVerified(true);
            $user->setRole($roles[$index % count($roles)]);
            $user->setCompany($companies[$index % count($companies)]);
            $user->setBirthday(new \DateTime('1990-01-01'));
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTimeImmutable());

            // Asignar regiones
            $user->addRegion($regions[$index % count($regions)]);
            if ($index % 2 === 0) {
                $user->addRegion($regions[($index + 1) % count($regions)]);
            }

            // Establecer contraseña por defecto
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'demo123');
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $users[] = $user;
            $io->text("Creado usuario: {$userData['name']} {$userData['last_name']}");
        }

        $this->entityManager->flush();
        return $users;
    }

    private function createBeneficiaries(SymfonyStyle $io, array $users): void
    {
        $beneficiariesData = [
            [
                'name' => 'Pedro',
                'last_name' => 'García Rodríguez',
                'kinship' => 'Hijo',
                'gender' => 'Masculino',
                'education' => 'Primaria',
                'curp' => 'GARP120315HDFRDR01',
                'birthday' => '2012-03-15'
            ],
            [
                'name' => 'Laura',
                'last_name' => 'García Rodríguez',
                'kinship' => 'Hija',
                'gender' => 'Femenino',
                'education' => 'Secundaria',
                'curp' => 'GARL100822MDFRDL02',
                'birthday' => '2010-08-22'
            ],
            [
                'name' => 'Roberto',
                'last_name' => 'Rodríguez Sánchez',
                'kinship' => 'Esposo',
                'gender' => 'Masculino',
                'education' => 'Licenciatura',
                'curp' => 'ROSR880512HDFRBN03',
                'birthday' => '1988-05-12'
            ],
            [
                'name' => 'Sofia',
                'last_name' => 'Hernández López',
                'kinship' => 'Hija',
                'gender' => 'Femenino',
                'education' => 'Preparatoria',
                'curp' => 'HELS050920MDFRLF04',
                'birthday' => '2005-09-20'
            ]
        ];

        foreach ($beneficiariesData as $index => $beneficiaryData) {
            $beneficiary = new Beneficiary();
            $beneficiary->setName($beneficiaryData['name']);
            $beneficiary->setLastName($beneficiaryData['last_name']);
            $beneficiary->setKinship($beneficiaryData['kinship']);
            $beneficiary->setGender($beneficiaryData['gender']);
            $beneficiary->setEducation($beneficiaryData['education']);
            $beneficiary->setCurp($beneficiaryData['curp']);
            $beneficiary->setBirthday(new \DateTime($beneficiaryData['birthday']));
            $beneficiary->setStatus(Status::ACTIVE);
            $beneficiary->setUser($users[$index % count($users)]);
            $beneficiary->setCreatedAt(new \DateTimeImmutable());
            $beneficiary->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($beneficiary);
            $io->text("Creado beneficiario: {$beneficiaryData['name']} {$beneficiaryData['last_name']}");
        }

        $this->entityManager->flush();
    }

    private function createFormTemplates(SymfonyStyle $io): array
    {
        $formTemplatesData = [
            [
                'name' => 'Evaluación de Desempeño',
                'description' => 'Formulario para evaluar el desempeño anual de los empleados',
                'fields' => [
                    ['label' => 'Nombre del Empleado', 'name' => 'employee_name', 'type' => 'text', 'required' => true],
                    ['label' => 'Puesto', 'name' => 'position', 'type' => 'text', 'required' => true],
                    ['label' => 'Calificación General', 'name' => 'overall_rating', 'type' => 'select', 'required' => true, 'options' => 'Excelente,Bueno,Regular,Deficiente'],
                    ['label' => 'Fortalezas', 'name' => 'strengths', 'type' => 'textarea', 'required' => false],
                    ['label' => 'Áreas de Mejora', 'name' => 'improvement_areas', 'type' => 'textarea', 'required' => false],
                    ['label' => 'Fecha de Evaluación', 'name' => 'evaluation_date', 'type' => 'date', 'required' => true]
                ]
            ],
            [
                'name' => 'Solicitud de Vacaciones',
                'description' => 'Formulario para solicitar días de vacaciones',
                'fields' => [
                    ['label' => 'Fecha de Inicio', 'name' => 'start_date', 'type' => 'date', 'required' => true],
                    ['label' => 'Fecha de Fin', 'name' => 'end_date', 'type' => 'date', 'required' => true],
                    ['label' => 'Días Solicitados', 'name' => 'days_requested', 'type' => 'number', 'required' => true],
                    ['label' => 'Motivo', 'name' => 'reason', 'type' => 'textarea', 'required' => false],
                    ['label' => 'Contacto de Emergencia', 'name' => 'emergency_contact', 'type' => 'text', 'required' => false]
                ]
            ],
            [
                'name' => 'Encuesta de Satisfacción Laboral',
                'description' => 'Encuesta para medir la satisfacción de los empleados',
                'fields' => [
                    ['label' => '¿Qué tan satisfecho estás con tu trabajo?', 'name' => 'job_satisfaction', 'type' => 'select', 'required' => true, 'options' => 'Muy satisfecho,Satisfecho,Neutral,Insatisfecho,Muy insatisfecho'],
                    ['label' => '¿Recomendarías esta empresa?', 'name' => 'would_recommend', 'type' => 'radio', 'required' => true, 'options' => 'Sí,No'],
                    ['label' => 'Califica el ambiente laboral', 'name' => 'work_environment', 'type' => 'select', 'required' => true, 'options' => '5 - Excelente,4 - Bueno,3 - Regular,2 - Malo,1 - Muy malo'],
                    ['label' => 'Comentarios adicionales', 'name' => 'additional_comments', 'type' => 'textarea', 'required' => false]
                ]
            ]
        ];

        $formTemplates = [];
        foreach ($formTemplatesData as $templateData) {
            $formTemplate = new FormTemplate();
            $formTemplate->setName($templateData['name']);
            $formTemplate->setDescription($templateData['description']);
            $formTemplate->setStatus(Status::ACTIVE);
            $formTemplate->setCreatedAt(new \DateTimeImmutable());
            $formTemplate->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($formTemplate);
            $this->entityManager->flush(); // Flush para obtener el ID

            // Crear campos del formulario
            foreach ($templateData['fields'] as $index => $fieldData) {
                $field = new FormTemplateField();
                $field->setFormTemplate($formTemplate);
                $field->setLabel($fieldData['label']);
                $field->setName($fieldData['name']);
                $field->setType($fieldData['type']);
                $field->setIsRequired($fieldData['required']);
                $field->setOptions($fieldData['options'] ?? null);
                $field->setSortOrder($index + 1);
                $field->setStatus(Status::ACTIVE);

                $this->entityManager->persist($field);
            }

            $formTemplates[] = $formTemplate;
            $io->text("Creada plantilla de formulario: {$templateData['name']}");
        }

        $this->entityManager->flush();
        return $formTemplates;
    }

    private function createFormEntries(SymfonyStyle $io, array $formTemplates, array $users): void
    {
        // Datos de ejemplo para las respuestas
        $sampleResponses = [
            'Evaluación de Desempeño' => [
                [
                    'employee_name' => 'Juan Carlos García López',
                    'position' => 'Desarrollador Senior',
                    'overall_rating' => 'Excelente',
                    'strengths' => 'Excelente capacidad técnica, liderazgo natural, proactivo',
                    'improvement_areas' => 'Mejorar comunicación con otros departamentos',
                    'evaluation_date' => '2024-12-01'
                ],
                [
                    'employee_name' => 'María Elena Rodríguez Martínez',
                    'position' => 'Gerente de Proyectos',
                    'overall_rating' => 'Bueno',
                    'strengths' => 'Organizada, buena gestión de tiempo, comunicación efectiva',
                    'improvement_areas' => 'Delegar más responsabilidades al equipo',
                    'evaluation_date' => '2024-12-02'
                ]
            ],
            'Solicitud de Vacaciones' => [
                [
                    'start_date' => '2024-12-20',
                    'end_date' => '2024-12-30',
                    'days_requested' => '8',
                    'reason' => 'Vacaciones de fin de año con familia',
                    'emergency_contact' => 'Ana García - 5551234567'
                ]
            ],
            'Encuesta de Satisfacción Laboral' => [
                [
                    'job_satisfaction' => 'Muy satisfecho',
                    'would_recommend' => 'Sí',
                    'work_environment' => '5 - Excelente',
                    'additional_comments' => 'Excelente ambiente de trabajo y oportunidades de crecimiento'
                ],
                [
                    'job_satisfaction' => 'Satisfecho',
                    'would_recommend' => 'Sí',
                    'work_environment' => '4 - Bueno',
                    'additional_comments' => 'Buen lugar para trabajar, aunque podría mejorar la comunicación interna'
                ]
            ]
        ];

        foreach ($formTemplates as $template) {
            if (!isset($sampleResponses[$template->getName()])) {
                continue;
            }

            $responses = $sampleResponses[$template->getName()];

            foreach ($responses as $index => $responseData) {
                $formEntry = new FormEntry();
                $formEntry->setFormTemplate($template);
                $formEntry->setUser($users[$index % count($users)]);
                $formEntry->setStatus(Status::ACTIVE);
                $formEntry->setCreatedAt(new \DateTimeImmutable());
                $formEntry->setUpdatedAt(new \DateTimeImmutable());

                $this->entityManager->persist($formEntry);
                $this->entityManager->flush(); // Flush para obtener el ID

                // Crear valores para cada campo
                foreach ($template->getFormTemplateFields() as $field) {
                    if (isset($responseData[$field->getName()])) {
                        $entryValue = new FormEntryValue();
                        $entryValue->setFormEntry($formEntry);
                        $entryValue->setFormTemplateField($field);
                        $entryValue->setValue($responseData[$field->getName()]);
                        $entryValue->setStatus(Status::ACTIVE);

                        $this->entityManager->persist($entryValue);
                    }
                }

                $io->text("Creada respuesta de formulario: {$template->getName()}");
            }
        }

        $this->entityManager->flush();
    }
}