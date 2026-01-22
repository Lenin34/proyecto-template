<?php

namespace App\Entity\App;

use App\Enum\PunchType;
use App\Repository\AttendancePunchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttendancePunchRepository::class)]
#[ORM\Table(name: 'attendance_punch')]
#[ORM\Index(columns: ['user_id', 'punch_datetime'], name: 'idx_punch_user_date')]
#[ORM\Index(columns: ['user_id', 'punch_datetime', 'punch_type'], name: 'idx_punch_calculation')]
#[ORM\Index(columns: ['company_id', 'punch_datetime'], name: 'idx_punch_company_date')]
class AttendancePunch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attendancePunches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La fecha y hora del punch es obligatoria.')]
    private ?\DateTimeInterface $punch_datetime = null;

    #[ORM\Column(type: 'string', length: 20, enumType: PunchType::class)]
    private ?PunchType $punch_type = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $location_data = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $device_info = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

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

    public function getPunchDatetime(): ?\DateTimeInterface
    {
        return $this->punch_datetime;
    }

    public function setPunchDatetime(\DateTimeInterface $punch_datetime): self
    {
        $this->punch_datetime = $punch_datetime;
        return $this;
    }

    public function getPunchType(): ?PunchType
    {
        return $this->punch_type;
    }

    public function setPunchType(PunchType $punch_type): self
    {
        $this->punch_type = $punch_type;
        return $this;
    }

    public function getLocationData(): ?array
    {
        return $this->location_data;
    }

    public function setLocationData(?array $location_data): self
    {
        $this->location_data = $location_data;
        return $this;
    }

    public function getDeviceInfo(): ?string
    {
        return $this->device_info;
    }

    public function setDeviceInfo(?string $device_info): self
    {
        $this->device_info = $device_info;
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
    public function getPunchDate(): string
    {
        return $this->punch_datetime?->format('Y-m-d') ?? '';
    }

    public function getPunchTime(): string
    {
        return $this->punch_datetime?->format('H:i:s') ?? '';
    }

    public function getFormattedPunchDateTime(): string
    {
        return $this->punch_datetime?->format('d/m/Y H:i:s') ?? '';
    }

    public function isCheckIn(): bool
    {
        return $this->punch_type === PunchType::CHECK_IN;
    }

    public function isCheckOut(): bool
    {
        return $this->punch_type === PunchType::CHECK_OUT;
    }

    public function isBreakStart(): bool
    {
        return $this->punch_type === PunchType::BREAK_START;
    }

    public function isBreakEnd(): bool
    {
        return $this->punch_type === PunchType::BREAK_END;
    }

    public function hasLocationData(): bool
    {
        return !empty($this->location_data);
    }

    public function getLatitude(): ?float
    {
        return $this->location_data['latitude'] ?? null;
    }

    public function getLongitude(): ?float
    {
        return $this->location_data['longitude'] ?? null;
    }

    public function getLocationAccuracy(): ?float
    {
        return $this->location_data['accuracy'] ?? null;
    }

    public function __toString(): string
    {
        $userName = $this->user?->getName() ?? 'Usuario';
        $type = $this->punch_type?->getLabel() ?? 'Punch';
        $time = $this->getFormattedPunchDateTime();
        
        return "{$userName} - {$type} - {$time}";
    }
}
