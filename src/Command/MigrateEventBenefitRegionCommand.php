<?php

namespace App\Command;

use App\Entity\App\Benefit;
use App\Entity\App\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-event-benefit-region',
    description: 'Migrate existing events and benefits to assign region based on their first associated company',
)]
class MigrateEventBenefitRegionCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Migrating Events and Benefits to assign Region');

        // Migrate Events
        $io->section('Processing Events');
        $eventsUpdated = $this->migrateEvents($io);
        
        // Migrate Benefits
        $io->section('Processing Benefits');
        $benefitsUpdated = $this->migrateBenefits($io);

        $io->success([
            "Migration completed successfully!",
            "Events updated: {$eventsUpdated}",
            "Benefits updated: {$benefitsUpdated}",
        ]);

        return Command::SUCCESS;
    }

    private function migrateEvents(SymfonyStyle $io): int
    {
        $eventRepository = $this->entityManager->getRepository(Event::class);
        $events = $eventRepository->createQueryBuilder('e')
            ->leftJoin('e.companies', 'c')
            ->leftJoin('c.region', 'r')
            ->where('e.region IS NULL')
            ->getQuery()
            ->getResult();

        $updated = 0;
        $skipped = 0;

        foreach ($events as $event) {
            $companies = $event->getCompanies();
            
            if ($companies->isEmpty()) {
                $io->warning("Event ID {$event->getId()} has no companies. Skipping.");
                $skipped++;
                continue;
            }

            // Get region from first company
            $firstCompany = $companies->first();
            $region = $firstCompany->getRegion();

            if (!$region) {
                $io->warning("Event ID {$event->getId()}: First company has no region. Skipping.");
                $skipped++;
                continue;
            }

            $event->setRegion($region);
            $this->entityManager->persist($event);
            $updated++;

            $io->writeln("✓ Event ID {$event->getId()} assigned to Region: {$region->getName()}");
        }

        $this->entityManager->flush();
        
        if ($skipped > 0) {
            $io->note("Skipped {$skipped} events without valid company/region associations.");
        }

        return $updated;
    }

    private function migrateBenefits(SymfonyStyle $io): int
    {
        $benefitRepository = $this->entityManager->getRepository(Benefit::class);
        $benefits = $benefitRepository->createQueryBuilder('b')
            ->leftJoin('b.companies', 'c')
            ->leftJoin('c.region', 'r')
            ->where('b.region IS NULL')
            ->getQuery()
            ->getResult();

        $updated = 0;
        $skipped = 0;

        foreach ($benefits as $benefit) {
            $companies = $benefit->getCompanies();
            
            if ($companies->isEmpty()) {
                $io->warning("Benefit ID {$benefit->getId()} has no companies. Skipping.");
                $skipped++;
                continue;
            }

            // Get region from first company
            $firstCompany = $companies->first();
            $region = $firstCompany->getRegion();

            if (!$region) {
                $io->warning("Benefit ID {$benefit->getId()}: First company has no region. Skipping.");
                $skipped++;
                continue;
            }

            $benefit->setRegion($region);
            $this->entityManager->persist($benefit);
            $updated++;

            $io->writeln("✓ Benefit ID {$benefit->getId()} assigned to Region: {$region->getName()}");
        }

        $this->entityManager->flush();
        
        if ($skipped > 0) {
            $io->note("Skipped {$skipped} benefits without valid company/region associations.");
        }

        return $updated;
    }
}
