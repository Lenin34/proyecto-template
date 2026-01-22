<?php

namespace App\Entity\App;

use App\Enum\Status;
use App\Repository\FormEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormEntryRepository::class)]
class FormEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'formEntries')]
    private ?FormTemplate $formTemplate = null;

    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: 'string', length: 1, enumType: Status::class)]
    private ?Status $status = null;

    /**
     * @var Collection<int, FormEntryValue>
     */
    #[ORM\OneToMany(targetEntity: FormEntryValue::class, mappedBy: 'formEntry')]
    private Collection $formEntryValues;

    #[ORM\ManyToOne]
    private ?Event $event = null;

    public function __construct()
    {
        $this->formEntryValues = new ArrayCollection();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

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
     * @return Collection<int, FormEntryValue>
     */
    public function getFormEntryValues(): Collection
    {
        return $this->formEntryValues;
    }

    public function addFormEntryValue(FormEntryValue $formEntryValue): self
    {
        if (!$this->formEntryValues->contains($formEntryValue)) {
            $this->formEntryValues->add($formEntryValue);
            $formEntryValue->setFormEntry($this);
        }

        return $this;
    }

    public function removeFormEntryValue(FormEntryValue $formEntryValue): self
    {
        if ($this->formEntryValues->removeElement($formEntryValue)) {
            // set the owning side to null (unless already changed)
            if ($formEntryValue->getFormEntry() === $this) {
                $formEntryValue->setFormEntry(null);
            }
        }

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

        return $this;
    }
}
