<?php

namespace App\Entity\App;

use App\Enum\Status;
use App\Repository\FormTemplateFieldRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormTemplateFieldRepository::class)]
class FormTemplateField
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'formTemplateFields', cascade: ['persist'])]
    private ?FormTemplate $formTemplate = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column]
    private ?bool $isRequired = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $options = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\Column(type: 'string', length: 1, enumType: Status::class)]
    private ?Status $status = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $help = null;

    #[ORM\Column(nullable: true)]
    private ?bool $multiple = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $cols = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $textareaCols = null;

    public function __construct()
    {
        $this->status = Status::ACTIVE;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFormTemplate(): ?FormTemplate
    {
        return $this->formTemplate;
    }

    public function setFormTemplate(?FormTemplate $formTemplate): self
    {
        $this->formTemplate = $formTemplate;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function isRequired(): ?bool
    {
        return $this->isRequired;
    }

    /**
     * Alias for isRequired() - needed for Twig PropertyAccessor compatibility
     */
    public function getIsRequired(): ?bool
    {
        return $this->isRequired();
    }

    public function setIsRequired(bool $isRequired): self
    {
        $this->isRequired = $isRequired;

        return $this;
    }

    public function getOptions(): ?string
    {
        return $this->options;
    }

    public function setOptions(?string $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

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

    public function getHelp(): ?string
    {
        return $this->help;
    }

    public function setHelp(?string $help): self
    {
        $this->help = $help;

        return $this;
    }

    public function isMultiple(): ?bool
    {
        return $this->multiple;
    }

    /**
     * Alias for isMultiple() - needed for Twig PropertyAccessor compatibility
     */
    public function getMultiple(): ?bool
    {
        return $this->isMultiple();
    }

    public function setMultiple(?bool $multiple): self
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function getCols(): ?string
    {
        return $this->cols;
    }

    public function setCols(?string $cols): self
    {
        $this->cols = $cols;

        return $this;
    }

    public function getTextareaCols(): ?string
    {
        return $this->textareaCols;
    }

    public function setTextareaCols(?string $textareaCols): self
    {
        $this->textareaCols = $textareaCols;

        return $this;
    }
}
