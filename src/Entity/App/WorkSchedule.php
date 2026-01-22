<?php

namespace App\Entity\App;

use App\Enum\Status;
use App\Repository\WorkScheduleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WorkScheduleRepository::class)]
#[ORM\Table(name: 'work_schedule')]
#[ORM\Index(columns: ['company_id', 'status'], name: 'idx_schedule_company_status')]
class WorkSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank(message: 'El nombre del horario es obligatorio.')]
    #[Assert\Length(max: 60, maxMessage: 'El nombre no puede exceder 60 caracteres.')]
    private ?string $name = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'La hora de inicio es obligatoria.')]
    private ?\DateTimeInterface $start_time = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'La hora de fin es obligatoria.')]
    private ?\DateTimeInterface $end_time = null;

    #[ORM\ManyToOne(inversedBy: 'workSchedules')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Company $company = null;

    #[ORM\Column(type: 'string', length: 1, enumType: Status::class)]
    private ?Status $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    /**
     * @var Collection<int, WorkScheduleDay>
     */
    #[ORM\OneToMany(targetEntity: WorkScheduleDay::class, mappedBy: 'workSchedule', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $workScheduleDays;

    /**
     * @var Collection<int, WorkScheduleBreak>
     */
    #[ORM\OneToMany(targetEntity: WorkScheduleBreak::class, mappedBy: 'workSchedule', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $workScheduleBreaks;

    /**
     * @var Collection<int, UserScheduleAssignment>
     */
    #[ORM\OneToMany(targetEntity: UserScheduleAssignment::class, mappedBy: 'workSchedule')]
    private Collection $userScheduleAssignments;

    public function __construct()
    {
        $this->workScheduleDays = new ArrayCollection();
        $this->workScheduleBreaks = new ArrayCollection();
        $this->userScheduleAssignments = new ArrayCollection();
        $this->status = Status::ACTIVE;
        $this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->start_time;
    }

    public function setStartTime(\DateTimeInterface $start_time): self
    {
        $this->start_time = $start_time;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->end_time;
    }

    public function setEndTime(\DateTimeInterface $end_time): self
    {
        $this->end_time = $end_time;
        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;
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

    /**
     * @return Collection<int, WorkScheduleDay>
     */
    public function getWorkScheduleDays(): Collection
    {
        return $this->workScheduleDays;
    }

    public function addWorkScheduleDay(WorkScheduleDay $workScheduleDay): self
    {
        if (!$this->workScheduleDays->contains($workScheduleDay)) {
            $this->workScheduleDays->add($workScheduleDay);
            $workScheduleDay->setWorkSchedule($this);
        }
        return $this;
    }

    public function removeWorkScheduleDay(WorkScheduleDay $workScheduleDay): self
    {
        if ($this->workScheduleDays->removeElement($workScheduleDay)) {
            if ($workScheduleDay->getWorkSchedule() === $this) {
                $workScheduleDay->setWorkSchedule(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, WorkScheduleBreak>
     */
    public function getWorkScheduleBreaks(): Collection
    {
        return $this->workScheduleBreaks;
    }

    public function addWorkScheduleBreak(WorkScheduleBreak $workScheduleBreak): self
    {
        if (!$this->workScheduleBreaks->contains($workScheduleBreak)) {
            $this->workScheduleBreaks->add($workScheduleBreak);
            $workScheduleBreak->setWorkSchedule($this);
        }
        return $this;
    }

    public function removeWorkScheduleBreak(WorkScheduleBreak $workScheduleBreak): self
    {
        if ($this->workScheduleBreaks->removeElement($workScheduleBreak)) {
            if ($workScheduleBreak->getWorkSchedule() === $this) {
                $workScheduleBreak->setWorkSchedule(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, UserScheduleAssignment>
     */
    public function getUserScheduleAssignments(): Collection
    {
        return $this->userScheduleAssignments;
    }

    public function addUserScheduleAssignment(UserScheduleAssignment $userScheduleAssignment): self
    {
        if (!$this->userScheduleAssignments->contains($userScheduleAssignment)) {
            $this->userScheduleAssignments->add($userScheduleAssignment);
            $userScheduleAssignment->setWorkSchedule($this);
        }
        return $this;
    }

    public function removeUserScheduleAssignment(UserScheduleAssignment $userScheduleAssignment): self
    {
        if ($this->userScheduleAssignments->removeElement($userScheduleAssignment)) {
            if ($userScheduleAssignment->getWorkSchedule() === $this) {
                $userScheduleAssignment->setWorkSchedule(null);
            }
        }
        return $this;
    }

    // Métodos de utilidad
    public function getTotalScheduledMinutes(): int
    {
        $start = $this->startTime;
        $end = $this->endTime;

        if (!$start || !$end) {
            return 0;
        }

        $diff = $end->diff($start);
        $totalMinutes = ($diff->h * 60) + $diff->i;

        // Restar tiempo de descansos no pagados
        foreach ($this->workScheduleBreaks as $break) {
            if (!$break->getIsPaid()) {
                $breakDiff = $break->getEndTime()->diff($break->getStartTime());
                $totalMinutes -= ($breakDiff->h * 60) + $breakDiff->i;
            }
        }

        return $totalMinutes;
    }

    public function isWorkingDay(int $dayOfWeek): bool
    {
        foreach ($this->workScheduleDays as $scheduleDay) {
            if ($scheduleDay->getDayOfWeek() === $dayOfWeek) {
                return $scheduleDay->getIsWorkingDay();
            }
        }
        return false;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
