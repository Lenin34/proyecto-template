<?php

namespace App\Entity\App;

use App\Repository\AttendanceAuditRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttendanceAuditRepository::class)]
#[ORM\Table(name: 'attendance_audit')]
#[ORM\Index(columns: ['attendance_record_id'], name: 'idx_audit_record')]
#[ORM\Index(columns: ['modified_by_user_id'], name: 'idx_audit_modifier')]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_date')]
class AttendanceAudit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attendanceAudits')]
    #[ORM\JoinColumn(name: 'attendance_record_id', nullable: false)]
    private ?AttendanceDailySummary $attendanceRecord = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $modified_by_user = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'El campo modificado es obligatorio.')]
    private ?string $field_changed = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $old_value = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $new_value = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'El motivo del cambio es obligatorio.')]
    private ?string $change_reason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
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

    public function getModifiedByUser(): ?User
    {
        return $this->modified_by_user;
    }

    public function setModifiedByUser(?User $modified_by_user): self
    {
        $this->modified_by_user = $modified_by_user;
        return $this;
    }

    public function getFieldChanged(): ?string
    {
        return $this->field_changed;
    }

    public function setFieldChanged(string $field_changed): self
    {
        $this->field_changed = $field_changed;
        return $this;
    }

    public function getOldValue(): ?string
    {
        return $this->old_value;
    }

    public function setOldValue(?string $old_value): self
    {
        $this->old_value = $old_value;
        return $this;
    }

    public function getNewValue(): ?string
    {
        return $this->new_value;
    }

    public function setNewValue(?string $new_value): self
    {
        $this->new_value = $new_value;
        return $this;
    }

    public function getChangeReason(): ?string
    {
        return $this->change_reason;
    }

    public function setChangeReason(string $change_reason): self
    {
        $this->change_reason = $change_reason;
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

    // Métodos de utilidad
    public function getFormattedCreatedDate(): string
    {
        return $this->created_at ? $this->created_at->format('d/m/Y H:i:s') : '';
    }

    public function getFieldLabel(): string
    {
        return match($this->field_changed) {
            'actual_start_time' => 'Hora de Entrada',
            'actual_end_time' => 'Hora de Salida',
            'total_worked_minutes' => 'Minutos Trabajados',
            'late_minutes' => 'Minutos de Retraso',
            'early_departure_minutes' => 'Minutos de Salida Temprana',
            'overtime_minutes' => 'Minutos de Tiempo Extra',
            'attendance_status' => 'Estado de Asistencia',
            'total_break_minutes' => 'Minutos de Descanso',
            default => ucfirst(str_replace('_', ' ', $this->field_changed)),
        };
    }

    public function getFormattedOldValue(): string
    {
        return $this->formatValue($this->old_value);
    }

    public function getFormattedNewValue(): string
    {
        return $this->formatValue($this->new_value);
    }

    private function formatValue(?string $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        // Formatear según el tipo de campo
        if (str_contains($this->field_changed, 'time') && $value !== 'N/A') {
            try {
                $time = new \DateTime($value);
                return $time->format('H:i:s');
            } catch (\Exception $e) {
                return $value;
            }
        }

        if (str_contains($this->field_changed, 'minutes')) {
            $minutes = (int) $value;
            $hours = intval($minutes / 60);
            $mins = $minutes % 60;
            
            if ($hours > 0) {
                return sprintf('%dh %02dm', $hours, $mins);
            }
            return sprintf('%dm', $mins);
        }

        return $value;
    }

    public function getChangeDescription(): string
    {
        $field = $this->getFieldLabel();
        $oldValue = $this->getFormattedOldValue();
        $newValue = $this->getFormattedNewValue();
        
        return "Campo '{$field}' cambió de '{$oldValue}' a '{$newValue}'";
    }

    public function __toString(): string
    {
        $modifier = $this->modified_by_user ? $this->modified_by_user->getName() : 'Usuario';
        $field = $this->getFieldLabel();
        $date = $this->getFormattedCreatedDate();
        
        return "{$modifier} modificó {$field} - {$date}";
    }
}
