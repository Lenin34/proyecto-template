<?php

namespace App\Entity\App;

use App\Repository\MessengerMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity para la tabla messenger_messages de Symfony Messenger
 * Esta tabla es utilizada por el transporte Doctrine de Messenger
 */
#[ORM\Entity(repositoryClass: MessengerMessageRepository::class)]
#[ORM\Table(name: 'messenger_messages')]
#[ORM\Index(name: 'IDX_75EA56E0FB7336F0', columns: ['queue_name'])]
#[ORM\Index(name: 'IDX_75EA56E0E3BD61CE', columns: ['available_at'])]
#[ORM\Index(name: 'IDX_75EA56E016BA31DB', columns: ['delivered_at'])]
class MessengerMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $body = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $headers = null;

    #[ORM\Column(type: Types::STRING, length: 190)]
    private ?string $queue_name = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $available_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $delivered_at = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getHeaders(): ?string
    {
        return $this->headers;
    }

    public function setHeaders(string $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    public function getQueueName(): ?string
    {
        return $this->queue_name;
    }

    public function setQueueName(string $queue_name): self
    {
        $this->queue_name = $queue_name;
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

    public function getAvailableAt(): ?\DateTimeInterface
    {
        return $this->available_at;
    }

    public function setAvailableAt(\DateTimeInterface $available_at): self
    {
        $this->available_at = $available_at;
        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeInterface
    {
        return $this->delivered_at;
    }

    public function setDeliveredAt(?\DateTimeInterface $delivered_at): self
    {
        $this->delivered_at = $delivered_at;
        return $this;
    }
}

