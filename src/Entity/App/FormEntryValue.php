<?php

namespace App\Entity\App;

use App\Enum\Status;
use App\Repository\FormEntryValueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormEntryValueRepository::class)]
class FormEntryValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'formEntryValues')]
    private ?FormEntry $formEntry = null;

    #[ORM\ManyToOne]
    private ?FormTemplateField $formTemplateField = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $value = null;

    #[ORM\Column(type: 'string', length: 1, enumType: Status::class)]
    private ?Status $status = null;

    public function __construct()
    {
        $this->status = Status::ACTIVE;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFormEntry(): ?FormEntry
    {
        return $this->formEntry;
    }

    public function setFormEntry(?FormEntry $formEntry): self
    {
        $this->formEntry = $formEntry;

        return $this;
    }

    public function getFormTemplateField(): ?FormTemplateField
    {
        return $this->formTemplateField;
    }

    public function setFormTemplateField(?FormTemplateField $formTemplateField): self
    {
        $this->formTemplateField = $formTemplateField;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

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
}
