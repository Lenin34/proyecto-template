<?php

namespace App\Entity\App;

use App\Enum\AttendanceStatus;
use App\Repository\AttendanceDailySummaryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttendanceDailySummaryRepository::class)]
#[ORM\Table(name: 'attendance_daily_summary')]
#[ORM\Index(columns: ['user_id', 'attendance_date'], name: 'idx_summary_user_date')]
#[ORM\Index(columns: ['company_id', 'attendance_date'], name: 'idx_summary_company_date')]
#[ORM\Index(columns: ['attendance_date', 'attendance_status'], name: 'idx_summary_date_status')]
#[ORM\UniqueConstraint(name: 'unique_user_date', columns: ['user_id', 'attendance_date'])]
class AttendanceDailySummary
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attendanceDailySummaries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?WorkSchedule $workSchedule = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La fecha de asistencia es obligatoria.')]
    private ?\DateTimeInterface $attendance_date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scheduled_start_time = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scheduled_end_time = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $actual_start_time = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $actual_end_time = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $total_scheduled_minutes = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $total_worked_minutes = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private ?int $total_break_minutes = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private ?int $late_minutes = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private ?int $early_departure_minutes = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private ?int $overtime_minutes = 0;

    #[ORM\Column(type: 'string', length: 25, enumType: AttendanceStatus::class)]
    private ?AttendanceStatus $attendance_status = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $is_calculated = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $calculated_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    /**
     * @var Collection<int, AttendanceException>
     */
    #[ORM\OneToMany(targetEntity: AttendanceException::class, mappedBy: 'attendanceRecord', orphanRemoval: true)]
    private Collection $attendanceExceptions;

    /**
     * @var Collection<int, AttendanceAudit>
     */
    #[ORM\OneToMany(targetEntity: AttendanceAudit::class, mappedBy: 'attendanceRecord', orphanRemoval: true)]
    private Collection $attendanceAudits;

    public function __construct()
    {
        $this->attendance_status = AttendanceStatus::PENDING_CALCULATION;
        $this->total_break_minutes = 0;
        $this->late_minutes = 0;
        $this->early_departure_minutes = 0;
        $this->overtime_minutes = 0;
        $this->is_calculated = false;
        $this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
        $this->attendanceExceptions = new ArrayCollection();
        $this->attendanceAudits = new ArrayCollection();
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

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;
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

    public function getAttendanceDate(): ?\DateTimeInterface
    {
        return $this->attendance_date;
    }

    public function setAttendanceDate(\DateTimeInterface $attendance_date): self
    {
        $this->attendance_date = $attendance_date;
        return $this;
    }

    public function getScheduledStartTime(): ?\DateTimeInterface
    {
        return $this->scheduled_start_time;
    }

    public function setScheduledStartTime(?\DateTimeInterface $scheduled_start_time): self
    {
        $this->scheduled_start_time = $scheduled_start_time;
        return $this;
    }

    public function getScheduledEndTime(): ?\DateTimeInterface
    {
        return $this->scheduled_end_time;
    }

    public function setScheduledEndTime(?\DateTimeInterface $scheduled_end_time): self
    {
        $this->scheduled_end_time = $scheduled_end_time;
        return $this;
    }

    public function getActualStartTime(): ?\DateTimeInterface
    {
        return $this->actual_start_time;
    }

    public function setActualStartTime(?\DateTimeInterface $actual_start_time): self
    {
        $this->actual_start_time = $actual_start_time;
        return $this;
    }

    public function getActualEndTime(): ?\DateTimeInterface
    {
        return $this->actual_end_time;
    }

    public function setActualEndTime(?\DateTimeInterface $actual_end_time): self
    {
        $this->actual_end_time = $actual_end_time;
        return $this;
    }

    public function getTotalScheduledMinutes(): ?int
    {
        return $this->total_scheduled_minutes;
    }

    public function setTotalScheduledMinutes(?int $total_scheduled_minutes): self
    {
        $this->total_scheduled_minutes = $total_scheduled_minutes;
        return $this;
    }

    public function getTotalWorkedMinutes(): ?int
    {
        return $this->total_worked_minutes;
    }

    public function setTotalWorkedMinutes(?int $total_worked_minutes): self
    {
        $this->total_worked_minutes = $total_worked_minutes;
        return $this;
    }

    public function getTotalBreakMinutes(): ?int
    {
        return $this->total_break_minutes;
    }

    public function setTotalBreakMinutes(int $total_break_minutes): self
    {
        $this->total_break_minutes = $total_break_minutes;
        return $this;
    }

    public function getLateMinutes(): ?int
    {
        return $this->late_minutes;
    }

    public function setLateMinutes(int $late_minutes): self
    {
        $this->late_minutes = $late_minutes;
        return $this;
    }

    public function getEarlyDepartureMinutes(): ?int
    {
        return $this->early_departure_minutes;
    }

    public function setEarlyDepartureMinutes(int $early_departure_minutes): self
    {
        $this->early_departure_minutes = $early_departure_minutes;
        return $this;
    }

    public function getOvertimeMinutes(): ?int
    {
        return $this->overtime_minutes;
    }

    public function setOvertimeMinutes(int $overtime_minutes): self
    {
        $this->overtime_minutes = $overtime_minutes;
        return $this;
    }

    public function getAttendanceStatus(): ?AttendanceStatus
    {
        return $this->attendance_status;
    }

    public function setAttendanceStatus(AttendanceStatus $attendance_status): self
    {
        $this->attendance_status = $attendance_status;
        return $this;
    }

    public function getIsCalculated(): ?bool
    {
        return $this->is_calculated;
    }

    public function setIsCalculated(bool $is_calculated): self
    {
        $this->is_calculated = $is_calculated;
        return $this;
    }

    public function getCalculatedAt(): ?\DateTimeInterface
    {
        return $this->calculated_at;
    }

    public function setCalculatedAt(?\DateTimeInterface $calculated_at): self
    {
        $this->calculated_at = $calculated_at;
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
     * @return Collection<int, AttendanceException>
     */
    public function getAttendanceExceptions(): Collection
    {
        return $this->attendanceExceptions;
    }

    public function addAttendanceException(AttendanceException $attendanceException): self
    {
        if (!$this->attendanceExceptions->contains($attendanceException)) {
            $this->attendanceExceptions->add($attendanceException);
            $attendanceException->setAttendanceRecord($this);
        }

        return $this;
    }

    public function removeAttendanceException(AttendanceException $attendanceException): self
    {
        if ($this->attendanceExceptions->removeElement($attendanceException)) {
            if ($attendanceException->getAttendanceRecord() === $this) {
                $attendanceException->setAttendanceRecord(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AttendanceAudit>
     */
    public function getAttendanceAudits(): Collection
    {
        return $this->attendanceAudits;
    }

    public function addAttendanceAudit(AttendanceAudit $attendanceAudit): self
    {
        if (!$this->attendanceAudits->contains($attendanceAudit)) {
            $this->attendanceAudits->add($attendanceAudit);
            $attendanceAudit->setAttendanceRecord($this);
        }

        return $this;
    }

    public function removeAttendanceAudit(AttendanceAudit $attendanceAudit): self
    {
        if ($this->attendanceAudits->removeElement($attendanceAudit)) {
            if ($attendanceAudit->getAttendanceRecord() === $this) {
                $attendanceAudit->setAttendanceRecord(null);
            }
        }

        return $this;
    }

    // Métodos de utilidad
    public function getFormattedDate(): string
    {
        return $this->attendance_date ? $this->attendance_date->format('d/m/Y') : '';
    }

    public function getTotalWorkedHours(): float
    {
        return $this->total_worked_minutes ? round($this->total_worked_minutes / 60, 2) : 0;
    }

    public function getTotalScheduledHours(): float
    {
        return $this->total_scheduled_minutes ? round($this->total_scheduled_minutes / 60, 2) : 0;
    }

    public function getOvertimeHours(): float
    {
        return $this->overtime_minutes ? round($this->overtime_minutes / 60, 2) : 0;
    }

    public function getLateHours(): float
    {
        return $this->late_minutes ? round($this->late_minutes / 60, 2) : 0;
    }

    public function isPresent(): bool
    {
        return $this->attendance_status === AttendanceStatus::PRESENT;
    }

    public function isAbsent(): bool
    {
        return $this->attendance_status === AttendanceStatus::ABSENT;
    }

    public function isLate(): bool
    {
        return $this->attendance_status === AttendanceStatus::LATE;
    }

    public function hasExceptions(): bool
    {
        return !$this->attendanceExceptions->isEmpty();
    }

    public function hasApprovedExceptions(): bool
    {
        foreach ($this->attendanceExceptions as $exception) {
            if ($exception->getStatus() === \App\Enum\Status::ACTIVE) {
                return true;
            }
        }
        return false;
    }

    public function getEfficiencyPercentage(): float
    {
        if (!$this->total_scheduled_minutes || $this->total_scheduled_minutes === 0) {
            return 0;
        }

        $workedMinutes = $this->total_worked_minutes ?? 0;
        return round(($workedMinutes / $this->total_scheduled_minutes) * 100, 2);
    }

    public function markAsCalculated(): void
    {
        $this->is_calculated = true;
        $this->calculated_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        $userName = $this->user ? $this->user->getName() : 'Usuario';
        $date = $this->getFormattedDate();
        $status = $this->attendance_status ? $this->attendance_status->getLabel() : 'Sin estado';

        return "{$userName} - {$date} - {$status}";
    }
}
