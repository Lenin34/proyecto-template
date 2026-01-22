<?php

namespace App\Entity\App;

use App\Repository\WorkScheduleDayRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WorkScheduleDayRepository::class)]
#[ORM\Table(name: 'work_schedule_day')]
#[ORM\Index(columns: ['work_schedule_id', 'day_of_week'], name: 'idx_schedule_day')]
#[ORM\UniqueConstraint(name: 'unique_schedule_day', columns: ['work_schedule_id', 'day_of_week'])]
class WorkScheduleDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'workScheduleDays')]
    #[ORM\JoinColumn(name: 'work_schedule_id', nullable: false)]
    private ?WorkSchedule $workSchedule = null;

    #[ORM\Column(name: 'day_of_week', type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 7, notInRangeMessage: 'El día de la semana debe estar entre 1 (Lunes) y 7 (Domingo).')]
    private ?int $dayOfWeek = null;

    #[ORM\Column(name: 'is_working_day')]
    private ?bool $isWorkingDay = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isWorkingDay = true;
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

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function getIsWorkingDay(): ?bool
    {
        return $this->isWorkingDay;
    }

    public function setIsWorkingDay(bool $isWorkingDay): self
    {
        $this->isWorkingDay = $isWorkingDay;
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
    public function getDayName(): string
    {
        return match($this->dayOfWeek) {
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
            default => 'Desconocido',
        };
    }

    public function getDayShortName(): string
    {
        return match($this->dayOfWeek) {
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb',
            7 => 'Dom',
            default => '???',
        };
    }

    public static function getDayOfWeekFromDate(\DateTimeInterface $date): int
    {
        $dayOfWeek = (int) $date->format('N'); // 1 = Monday, 7 = Sunday
        return $dayOfWeek;
    }

    public function __toString(): string
    {
        return $this->getDayName() . ' (' . ($this->isWorkingDay ? 'Laboral' : 'No laboral') . ')';
    }
}
