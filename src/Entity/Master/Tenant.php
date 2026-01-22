<?php

namespace App\Entity\Master;

use App\Enum\Status;
use App\Repository\TenantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TenantRepository::class)]
class Tenant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 50)]
    private ?string $dominio = null;
    #[ORM\Column(length: 255)]
    private ?string $databaseName = null;
    #[ORM\Column(type: Types::JSON)]
    private array $features = [];
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $logo = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aviso = null;
    #[ORM\Column(type: 'string', length: 2, enumType: Status::class)]
    private ?Status $status = null;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getDominio(): ?string
    {
        return $this->dominio;
    }

    public function setDominio(?string $dominio): void
    {
        $this->dominio = $dominio;
    }

    public function getDatabaseName(): ?string
    {
        return $this->databaseName;
    }

    public function setDatabaseName(?string $databaseName): void
    {
        $this->databaseName = $databaseName;
    }

    public function getFeatures(): array
    {
        return $this->features;
    }

    public function setFeatures(array $features): void
    {
        $this->features = $features;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): void
    {
        $this->logo = $logo;
    }

    public function getAviso(): ?string
    {
        return $this->aviso;
    }

    public function setAviso(?string $aviso): void
    {
        $this->aviso = $aviso;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(?Status $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeInterface $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeInterface $updated_at): void
    {
        $this->updated_at = $updated_at;
    }

}
