<?php

namespace App\Entity\App;

use App\Enum\Status;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: "User")]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Company $company = null;

    #[ORM\Column(length: 100)]
    private ?string $last_name = null;

    #[ORM\Column(type: 'string', length: 1, enumType: Status::class)]
    private ?Status $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $google_auth = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $curp = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE , nullable: true)]
    private ?\DateTimeInterface $last_seen = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $birthday = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $phone_number = null;

    #[ORM\Column(length: 100, unique: false, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $gender = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $education = null;

    #[ORM\Column(nullable: true)]
    private ?bool $verified = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $verification_code = null;

    /**
     * @var Collection<int, History>
     */
    #[ORM\OneToMany(targetEntity: History::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $histories;

    /**
     * @var Collection<int, Beneficiary>
     */
    #[ORM\OneToMany(targetEntity: Beneficiary::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $beneficiaries;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Role $role = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $employee_number = null;

    /**
     * @var Collection<int, Region>
     */
    #[ORM\ManyToMany(targetEntity: Region::class, inversedBy: "users")]
    #[ORM\JoinTable(name: "region_user")]
    private Collection $regions;

    /**
     * @var Collection<int, DeviceToken>
     */
    #[ORM\OneToMany(targetEntity: DeviceToken::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $deviceTokens;

    /**
     * @var Collection<int, Conversation>
     */
    #[ORM\ManyToMany(targetEntity: Conversation::class, mappedBy: 'users')]
    private Collection $conversations;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'author')]
    private Collection $messages;

    /**
     * @var Collection<int, UserScheduleAssignment>
     */
    #[ORM\OneToMany(targetEntity: UserScheduleAssignment::class, mappedBy: 'user')]
    private Collection $userScheduleAssignments;

    /**
     * @var Collection<int, AttendancePunch>
     */
    #[ORM\OneToMany(targetEntity: AttendancePunch::class, mappedBy: 'user')]
    private Collection $attendancePunches;

    /**
     * @var Collection<int, AttendanceDailySummary>
     */
    #[ORM\OneToMany(targetEntity: AttendanceDailySummary::class, mappedBy: 'user')]
    private Collection $attendanceDailySummaries;

    public function __construct()
    {
        $this->histories = new ArrayCollection();
        $this->beneficiaries = new ArrayCollection();
        $this->deviceTokens = new ArrayCollection();
        $this->regions = new ArrayCollection();
        $this->conversations = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->userScheduleAssignments = new ArrayCollection();
        $this->attendancePunches = new ArrayCollection();
        $this->attendanceDailySummaries = new ArrayCollection();
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

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

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

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(string $last_name): self
    {
        $this->last_name = $last_name;

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

    public function getGoogleAuth(): ?string
    {
        return $this->google_auth;
    }

    public function setGoogleAuth(string $google_auth): self
    {
        $this->google_auth = $google_auth;

        return $this;
    }

    public function getCurp(): ?string
    {
        return $this->curp;
    }

    public function setCurp(string $curp): self
    {
        $this->curp = $curp;

        return $this;
    }

    public function getLastSeen(): ?\DateTimeInterface
    {
        return $this->last_seen;
    }

    public function setLastSeen(\DateTimeInterface $last_seen): self
    {
        $this->last_seen = $last_seen;

        return $this;
    }

    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(\DateTimeInterface $birthday): self
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): self
    {
        $this->photo = $photo;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phone_number;
    }

    public function setPhoneNumber(string $phone_number): self
    {
        $this->phone_number = $phone_number;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

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

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    public function getEducation(): ?string
    {
        return $this->education;
    }

    public function setEducation(?string $education): self
    {
        $this->education = $education;

        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): self
    {
        $this->verified = $verified;

        return $this;
    }

    public function getVerificationCode(): ?string
    {
        return $this->verification_code;
    }

    public function setVerificationCode(?string $verification_code): self
    {
        $this->verification_code = $verification_code;

        return $this;
    }

    /**
     * @return Collection<int, History>
     */
    public function getHistories(): Collection
    {
        return $this->histories;
    }

    public function addHistory(History $history): self
    {
        if (!$this->histories->contains($history)) {
            $this->histories->add($history);
            $history->setUser($this);
        }

        return $this;
    }

    public function removeHistory(History $history): self
    {
        if ($this->histories->removeElement($history)) {
            // set the owning side to null (unless already changed)
            if ($history->getUser() === $this) {
                $history->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Beneficiary>
     */
    public function getBeneficiaries(): Collection
    {
        return $this->beneficiaries;
    }

    public function addBeneficiary(Beneficiary $beneficiary): self
    {
        if (!$this->beneficiaries->contains($beneficiary)) {
            $this->beneficiaries->add($beneficiary);
            $beneficiary->setUser($this);
        }

        return $this;
    }

    public function removeBeneficiary(Beneficiary $beneficiary): self
    {
        if ($this->beneficiaries->removeElement($beneficiary)) {
            // set the owning side to null (unless already changed)
            if ($beneficiary->getUser() === $this) {
                $beneficiary->setUser(null);
            }
        }

        return $this;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getRoles(): array
    {
        if ($this->role === null) {
            return ['ROLE_USER']; // Rol por defecto si no hay role asignado
        }
        return [$this->role->getName()];
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
        // Do nothing, as we don't store any temporary sensitive data on the user
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function needsRehash(string $hashedPassword): bool
    {
        // Implement your logic to check if the password needs rehashing
        return password_needs_rehash($hashedPassword, PASSWORD_BCRYPT);
    }

    public function hash(string $plainPassword): string
    {
        // Implement your password hashing logic here
        return password_hash($plainPassword, PASSWORD_BCRYPT);
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        // Implement your password verification logic here
        return password_verify($plainPassword, $hashedPassword);
    }

    public function getEmployeeNumber(): ?string
    {
        return $this->employee_number;
    }

    public function setEmployeeNumber(string $employee_number): self
    {
        $this->employee_number = $employee_number;

        return $this;
    }

    /**
     * @return Collection<int, DeviceToken>
     */
    public function getDeviceTokens(): Collection
    {
        return $this->deviceTokens;
    }

    public function addDeviceToken(DeviceToken $deviceToken): self
    {
        if (!$this->deviceTokens->contains($deviceToken)) {
            $this->deviceTokens->add($deviceToken);
            $deviceToken->setUser($this);
        }

        return $this;
    }

    public function removeDeviceToken(DeviceToken $deviceToken): self
    {
        if ($this->deviceTokens->removeElement($deviceToken)) {
            // set the owning side to null (unless already changed)
            if ($deviceToken->getUser() === $this) {
                $deviceToken->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Region>
     */
    public function getRegions(): Collection
    {
        return $this->regions;
    }

    public function addRegion(Region $region): self
    {
        if (!$this->regions->contains($region)) {
            $this->regions->add($region);
            // Update the owning side of the relationship
            $region->addUser($this);
        }

        return $this;
    }

    public function removeRegion(Region $region): self
    {
        if ($this->regions->removeElement($region)) {
            // Update the owning side of the relationship
            $region->removeUser($this);
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        if ($this->created_at === null) {
            $this->created_at = $now;
        }
        if ($this->updated_at === null) {
            $this->updated_at = $now;
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated_at = new \DateTimeImmutable();
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
            $conversation->addUser($this);
        }

        return $this;
    }

    public function removeConversation(Conversation $conversation): self
    {
        if ($this->conversations->removeElement($conversation)) {
            $conversation->removeUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setAuthor($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getAuthor() === $this) {
                $message->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserScheduleAssignment>
     */
    public function getUserScheduleAssignments(): Collection
    {
        return $this->userScheduleAssignments;
    }

    public function addUserScheduleAssignment(UserScheduleAssignment $userScheduleAssignment): self
    {
        if (!$this->userScheduleAssignments->contains($userScheduleAssignment)) {
            $this->userScheduleAssignments->add($userScheduleAssignment);
            $userScheduleAssignment->setUser($this);
        }

        return $this;
    }

    public function removeUserScheduleAssignment(UserScheduleAssignment $userScheduleAssignment): self
    {
        if ($this->userScheduleAssignments->removeElement($userScheduleAssignment)) {
            if ($userScheduleAssignment->getUser() === $this) {
                $userScheduleAssignment->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AttendancePunch>
     */
    public function getAttendancePunches(): Collection
    {
        return $this->attendancePunches;
    }

    public function addAttendancePunch(AttendancePunch $attendancePunch): self
    {
        if (!$this->attendancePunches->contains($attendancePunch)) {
            $this->attendancePunches->add($attendancePunch);
            $attendancePunch->setUser($this);
        }

        return $this;
    }

    public function removeAttendancePunch(AttendancePunch $attendancePunch): self
    {
        if ($this->attendancePunches->removeElement($attendancePunch)) {
            if ($attendancePunch->getUser() === $this) {
                $attendancePunch->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AttendanceDailySummary>
     */
    public function getAttendanceDailySummaries(): Collection
    {
        return $this->attendanceDailySummaries;
    }

    public function addAttendanceDailySummary(AttendanceDailySummary $attendanceDailySummary): self
    {
        if (!$this->attendanceDailySummaries->contains($attendanceDailySummary)) {
            $this->attendanceDailySummaries->add($attendanceDailySummary);
            $attendanceDailySummary->setUser($this);
        }

        return $this;
    }

    public function removeAttendanceDailySummary(AttendanceDailySummary $attendanceDailySummary): self
    {
        if ($this->attendanceDailySummaries->removeElement($attendanceDailySummary)) {
            if ($attendanceDailySummary->getUser() === $this) {
                $attendanceDailySummary->setUser(null);
            }
        }

        return $this;
    }
}
