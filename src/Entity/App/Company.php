<?php

namespace App\Entity\App;

use App\Enum\Status;
use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 1, enumType: Status::class)]
    private ?Status $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\ManyToOne(inversedBy: 'companies')]
    private ?Region $region = null;

    /**
     * @var Collection<int, Benefit>
     */
    #[ORM\ManyToMany(targetEntity: Benefit::class, mappedBy: 'companies')]
    private Collection $benefits;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\ManyToMany(targetEntity: Event::class, mappedBy: 'companies')]
    private Collection $events;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'company', orphanRemoval: true)]
    private Collection $users;

    /**
     * @var Collection<int, SocialMedia>
     */
    #[ORM\ManyToMany(targetEntity: SocialMedia::class, mappedBy: 'companies')]
    private Collection $socialMedia;

    /**
     * @var Collection<int, Conversation>
     */
    #[ORM\OneToMany(targetEntity: Conversation::class, mappedBy: 'company')]
    private Collection $conversations;

    /**
     * @var Collection<int, FormTemplate>
     */
    #[ORM\ManyToMany(targetEntity: FormTemplate::class, mappedBy: 'companies')]
    private Collection $formTemplates;

    /**
     * @var Collection<int, WorkSchedule>
     */
    #[ORM\OneToMany(targetEntity: WorkSchedule::class, mappedBy: 'company')]
    private Collection $workSchedules;

    public function __construct()
    {
        $this->benefits = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->socialMedia = new ArrayCollection();
        $this->conversations = new ArrayCollection();
        $this->formTemplates = new ArrayCollection();
        $this->workSchedules = new ArrayCollection();
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

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): self
    {
        $this->status = $status;

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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    /**
     * @return Collection<int, Benefit>
     */
    public function getBenefits(): Collection
    {
        return $this->benefits;
    }

    public function addBenefit(Benefit $benefit): self
    {
        if (!$this->benefits->contains($benefit)) {
            $this->benefits[] = $benefit;
            $benefit->addCompany($this);
        }
        return $this;
    }

    public function removeBenefit(Benefit $benefit): self
    {
        if ($this->benefits->removeElement($benefit)) {
            $benefit->removeCompany($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events[] = $event;
            $event->addCompany($this);
        }
        return $this;
    }

    public function removeEvent(Event $event): self
    {
        if ($this->events->removeElement($event)) {
            $event->removeCompany($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setCompany($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getCompany() === $this) {
                $user->setCompany(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SocialMedia>
     */
    public function getSocialMedia(): Collection
    {
        return $this->socialMedia;
    }

    public function addSocialMedia(SocialMedia $socialMedia): self
    {
        if (!$this->socialMedia->contains($socialMedia)) {
            $this->socialMedia->add($socialMedia);
            $socialMedia->addCompany($this);
        }

        return $this;
    }

    public function removeSocialMedia(SocialMedia $socialMedia): self
    {
        if ($this->socialMedia->removeElement($socialMedia)) {
            $socialMedia->removeCompany($this);
        }

        return $this;
    }

    public function getRegion(): ?Region
    {
        return $this->region;
    }

    public function setRegion(?Region $region): self
    {
        $this->region = $region;

        return $this;
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversations(): Collection
    {
        return $this->conversations;
    }

    public function addConversation(Conversation $conversation): self
    {
        if (!$this->conversations->contains($conversation)) {
            $this->conversations->add($conversation);
            $conversation->setCompany($this);
        }

        return $this;
    }

    public function removeConversation(Conversation $conversation): self
    {
        if ($this->conversations->removeElement($conversation)) {
            // set the owning side to null (unless already changed)
            if ($conversation->getCompany() === $this) {
                $conversation->setCompany(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FormTemplate>
     */
    public function getFormTemplates(): Collection
    {
        return $this->formTemplates;
    }

    public function addFormTemplate(FormTemplate $formTemplate): self
    {
        if (!$this->formTemplates->contains($formTemplate)) {
            $this->formTemplates->add($formTemplate);
            $formTemplate->addCompany($this);
        }

        return $this;
    }

    public function removeFormTemplate(FormTemplate $formTemplate): self
    {
        if ($this->formTemplates->removeElement($formTemplate)) {
            $formTemplate->removeCompany($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, WorkSchedule>
     */
    public function getWorkSchedules(): Collection
    {
        return $this->workSchedules;
    }

    public function addWorkSchedule(WorkSchedule $workSchedule): self
    {
        if (!$this->workSchedules->contains($workSchedule)) {
            $this->workSchedules->add($workSchedule);
            $workSchedule->setCompany($this);
        }

        return $this;
    }

    public function removeWorkSchedule(WorkSchedule $workSchedule): self
    {
        if ($this->workSchedules->removeElement($workSchedule)) {
            if ($workSchedule->getCompany() === $this) {
                $workSchedule->setCompany(null);
            }
        }

        return $this;
    }
}
