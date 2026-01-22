<?php

namespace App\Entity\App;

use App\Repository\WorkScheduleBreakRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WorkScheduleBreakRepository::class)]
#[ORM\Table(name: 'work_schedule_break')]
#[ORM\Index(columns: ['work_schedule_id'], name: 'idx_schedule_break')]
class WorkScheduleBreak
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'workScheduleBreaks')]
    #[ORM\JoinColumn(name: 'work_schedule_id', nullable: false)]
    private ?WorkSchedule $workSchedule = null;

    #[ORM\Column(name: 'break_name', length: 50)]
    #[Assert\NotBlank(message: 'El nombre del descanso es obligatorio.')]
    #[Assert\Length(max: 50, maxMessage: 'El nombre no puede exceder 50 caracteres.')]
    private ?string $breakName = null;

    #[ORM\Column(name: 'start_time', type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'La hora de inicio del descanso es obligatoria.')]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(name: 'end_time', type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'La hora de fin del descanso es obligatoria.')]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(name: 'is_paid')]
    private ?bool $isPaid = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isPaid = false;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getBreakName(): ?string
    {
        return $this->breakName;
    }

    public function setBreakName(string $breakName): self
    {
        $this->breakName = $breakName;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getIsPaid(): ?bool
    {
        return $this->isPaid;
    }

    public function setIsPaid(bool $isPaid): self
    {
        $this->isPaid = $isPaid;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // Métodos de utilidad
    public function getDurationMinutes(): int
    {
        if (!$this->startTime || !$this->endTime) {
            return 0;
        }

        $diff = $this->endTime->diff($this->startTime);
        return ($diff->h * 60) + $diff->i;
    }

    public function getFormattedDuration(): string
    {
        $minutes = $this->getDurationMinutes();
        $hours = intval($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $mins);
        }
        return sprintf('%dm', $mins);
    }

    public function isTimeInBreak(\DateTimeInterface $time): bool
    {
        if (!$this->startTime || !$this->endTime) {
            return false;
        }

        $timeStr = $time->format('H:i:s');
        $startStr = $this->startTime->format('H:i:s');
        $endStr = $this->endTime->format('H:i:s');

        return $timeStr >= $startStr && $timeStr <= $endStr;
    }

    public function __toString(): string
    {
        return $this->breakName ?? '';
    }
}
