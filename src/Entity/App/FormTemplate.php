<?php

namespace App\Entity\App;

use App\Enum\Status;
use App\Repository\FormTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormTemplateRepository::class)]
class FormTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    /**
     * @var Collection<int, FormTemplateField>
     */
    #[ORM\OneToMany(targetEntity: FormTemplateField::class, mappedBy: 'formTemplate', cascade: ['persist', 'remove'])]
    private Collection $formTemplateFields;

    /**
     * @var Collection<int, FormEntry>
     */
    #[ORM\OneToMany(targetEntity: FormEntry::class, mappedBy: 'formTemplate')]
    private Collection $formEntries;

    #[ORM\Column(type: 'string', length: 1, enumType: Status::class)]
    private ?Status $status = null;

    /**
     * @var Collection<int, Company>
     */
    #[ORM\ManyToMany(targetEntity: Company::class, inversedBy: 'formTemplates')]
    #[ORM\JoinTable(name: 'form_template_company')]
    private Collection $companies;

    public function __construct()
    {
        $this->formTemplateFields = new ArrayCollection();
        $this->formEntries = new ArrayCollection();
        $this->companies = new ArrayCollection();
        $this->status = Status::ACTIVE;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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
     * @return Collection<int, FormTemplateField>
     */
    public function getFormTemplateFields(): Collection
    {
        return $this->formTemplateFields;
    }

    public function addFormTemplateField(FormTemplateField $formTemplateField): self
    {
        if (!$this->formTemplateFields->contains($formTemplateField)) {
            $this->formTemplateFields->add($formTemplateField);
            $formTemplateField->setFormTemplate($this);
        }

        return $this;
    }

    public function removeFormTemplateField(FormTemplateField $formTemplateField): self
    {
        if ($this->formTemplateFields->removeElement($formTemplateField)) {
            // set the owning side to null (unless already changed)
            if ($formTemplateField->getFormTemplate() === $this) {
                $formTemplateField->setFormTemplate(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FormEntry>
     */
    public function getFormEntries(): Collection
    {
        return $this->formEntries;
    }

    public function addFormEntry(FormEntry $formEntry): self
    {
        if (!$this->formEntries->contains($formEntry)) {
            $this->formEntries->add($formEntry);
            $formEntry->setFormTemplate($this);
        }

        return $this;
    }

    public function removeFormEntry(FormEntry $formEntry): self
    {
        if ($this->formEntries->removeElement($formEntry)) {
            // set the owning side to null (unless already changed)
            if ($formEntry->getFormTemplate() === $this) {
                $formEntry->setFormTemplate(null);
            }
        }

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



    /**
     * @return Collection<int, Company>
     */
    public function getCompanies(): Collection
    {
        return $this->companies;
    }

    public function addCompany(Company $company): self
    {
        if (!$this->companies->contains($company)) {
            $this->companies->add($company);
        }

        return $this;
    }

    public function removeCompany(Company $company): self
    {
        $this->companies->removeElement($company);

        return $this;
    }

    /**
     * Get company names as a string (similar to Event entity pattern)
     */
    public function getCompanyNames(): string
    {
        if ($this->companies->isEmpty()) {
            return 'Todas las empresas';
        }

        return implode(', ', $this->companies->map(fn($c) => $c->getName())->toArray());
    }

    /**
     * Check if form is available for all companies
     */
    public function isAvailableForAllCompanies(): bool
    {
        return $this->companies->isEmpty();
    }

    /**
     * Check if form is available for specific company
     */
    public function isAvailableForCompany(Company $company): bool
    {
        return $this->companies->isEmpty() || $this->companies->contains($company);
    }

    /**
     * Get count of active form entries (responses)
     */
    public function getResponsesCount(): int
    {
        return $this->formEntries->filter(function($entry) {
            return $entry->getStatus() === Status::ACTIVE;
        })->count();
    }
}
