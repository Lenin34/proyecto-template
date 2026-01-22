<?php

namespace App\Entity\App;

use App\Enum\ExceptionType;
use App\Enum\Status;
use App\Repository\AttendanceExceptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttendanceExceptionRepository::class)]
#[ORM\Table(name: 'attendance_exception')]
#[ORM\Index(columns: ['attendance_record_id'], name: 'idx_exception_record')]
#[ORM\Index(columns: ['exception_type', 'status'], name: 'idx_exception_type_status')]
class AttendanceException
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attendanceExceptions')]
    #[ORM\JoinColumn(name: 'attendance_record_id', nullable: false)]
    private ?AttendanceDailySummary $attendanceRecord = null;

    #[ORM\Column(type: 'string', length: 25, enumType: ExceptionType::class)]
    private ?ExceptionType $exception_type = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'El motivo de la excepción es obligatorio.')]
    private ?string $reason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $approved_by_user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $approval_date = null;

    #[ORM\Column(type: 'string', length: 1, enumType: Status::class)]
    private ?Status $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $approval_notes = null;

    public function __construct()
    {
        $this->status = Status::INACTIVE; // Pendiente de aprobación
        $this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAttendanceRecord(): ?AttendanceDailySummary
    {
        return $this->attendanceRecord;
    }

    public function setAttendanceRecord(?AttendanceDailySummary $attendanceRecord): self
    {
        $this->attendanceRecord = $attendanceRecord;
        return $this;
    }

    public function getExceptionType(): ?ExceptionType
    {
        return $this->exception_type;
    }

    public function setExceptionType(ExceptionType $exception_type): self
    {
        $this->exception_type = $exception_type;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function getApprovedByUser(): ?User
    {
        return $this->approved_by_user;
    }

    public function setApprovedByUser(?User $approved_by_user): self
    {
        $this->approved_by_user = $approved_by_user;
        return $this;
    }

    public function getApprovalDate(): ?\DateTimeInterface
    {
        return $this->approval_date;
    }

    public function setApprovalDate(?\DateTimeInterface $approval_date): self
    {
        $this->approval_date = $approval_date;
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

    public function getApprovalNotes(): ?string
    {
        return $this->approval_notes;
    }

    public function setApprovalNotes(?string $approval_notes): self
    {
        $this->approval_notes = $approval_notes;
        return $this;
    }

    // Métodos de utilidad
    public function isPending(): bool
    {
        return $this->status === Status::INACTIVE;
    }

    public function isApproved(): bool
    {
        return $this->status === Status::ACTIVE;
    }

    public function isRejected(): bool
    {
        return $this->status === Status::DELETED;
    }

    public function requiresApproval(): bool
    {
        return $this->exception_type ? $this->exception_type->requiresApproval() : false;
    }

    public function approve(User $approver, ?string $notes = null): void
    {
        $this->status = Status::ACTIVE;
        $this->approved_by_user = $approver;
        $this->approval_date = new \DateTimeImmutable();
        $this->approval_notes = $notes;
        $this->updated_at = new \DateTimeImmutable();
    }

    public function reject(User $approver, ?string $notes = null): void
    {
        $this->status = Status::DELETED;
        $this->approved_by_user = $approver;
        $this->approval_date = new \DateTimeImmutable();
        $this->approval_notes = $notes;
        $this->updated_at = new \DateTimeImmutable();
    }

    public function getFormattedCreatedDate(): string
    {
        return $this->created_at ? $this->created_at->format('d/m/Y H:i') : '';
    }

    public function getFormattedApprovalDate(): string
    {
        return $this->approval_date ? $this->approval_date->format('d/m/Y H:i') : '';
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            Status::INACTIVE => 'Pendiente',
            Status::ACTIVE => 'Aprobada',
            Status::DELETED => 'Rechazada',
            default => 'Desconocido',
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            Status::INACTIVE => 'badge-warning',
            Status::ACTIVE => 'badge-success',
            Status::DELETED => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    public function __toString(): string
    {
        $type = $this->exception_type ? $this->exception_type->getLabel() : 'Excepción';
        $date = $this->attendanceRecord ? $this->attendanceRecord->getFormattedDate() : '';
        $status = $this->getStatusLabel();
        
        return "{$type} - {$date} - {$status}";
    }
}
