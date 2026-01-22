<?php

namespace App\Entity\App;

use App\Enum\Status;
use App\Repository\UserScheduleAssignmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserScheduleAssignmentRepository::class)]
#[ORM\Table(name: 'user_schedule_assignment')]
#[ORM\Index(columns: ['user_id', 'effective_from', 'effective_until'], name: 'idx_user_schedule_period')]
#[ORM\Index(columns: ['work_schedule_id'], name: 'idx_assignment_schedule')]
class UserScheduleAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userScheduleAssignments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userScheduleAssignments')]
    #[ORM\JoinColumn(name: 'work_schedule_id', nullable: false)]
    private ?WorkSchedule $workSchedule = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La fecha de inicio es obligatoria.')]
    private ?\DateTimeInterface $effective_from = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effective_until = null;

    #[ORM\Column(type: 'string', length: 1, enumType: Status::class)]
    private ?Status $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->status = Status::ACTIVE;
        $this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getWorkSchedule(): ?WorkSchedule
    {
        return $this->workSchedule;
    }

    public function setWorkSchedule(?WorkSchedule $workSchedule): self
    {
        $this->workSchedule = $workSchedule;
        return $this;
    }

    public function getEffectiveFrom(): ?\DateTimeInterface
    {
        return $this->effective_from;
    }

    public function setEffectiveFrom(\DateTimeInterface $effective_from): self
    {
        $this->effective_from = $effective_from;
        return $this;
    }

    public function getEffectiveUntil(): ?\DateTimeInterface
    {
        return $this->effective_until;
    }

    public function setEffectiveUntil(?\DateTimeInterface $effective_until): self
    {
        $this->effective_until = $effective_until;
        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    // Métodos de utilidad
    public function isActiveForDate(\DateTimeInterface $date): bool
    {
        if ($this->status !== Status::ACTIVE) {
            return false;
        }

        $dateOnly = $date->format('Y-m-d');
        $effectiveFrom = $this->effective_from->format('Y-m-d');
        
        if ($dateOnly < $effectiveFrom) {
            return false;
        }

        if ($this->effective_until !== null) {
            $effectiveUntil = $this->effective_until->format('Y-m-d');
            if ($dateOnly > $effectiveUntil) {
                return false;
            }
        }

        return true;
    }

    public function isCurrentlyActive(): bool
    {
        return $this->isActiveForDate(new \DateTimeImmutable());
    }

    public function getDurationDays(): ?int
    {
        if (!$this->effective_from) {
            return null;
        }

        $endDate = $this->effective_until ?? new \DateTimeImmutable();
        $diff = $endDate->diff($this->effective_from);
        
        return $diff->days;
    }

    public function getFormattedPeriod(): string
    {
        $from = $this->effective_from?->format('d/m/Y') ?? '';
        
        if ($this->effective_until) {
            $until = $this->effective_until->format('d/m/Y');
            return "{$from} - {$until}";
        }
        
        return "{$from} - Indefinido";
    }

    public function __toString(): string
    {
        $userName = $this->user?->getName() ?? 'Usuario';
        $scheduleName = $this->workSchedule?->getName() ?? 'Horario';
        return "{$userName} - {$scheduleName}";
    }
}
