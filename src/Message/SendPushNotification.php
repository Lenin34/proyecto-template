<?php

namespace App\Message;

class SendPushNotification
{
    private string $title;
    private string $message;
    private array $deviceTokens;
    private int $notificationId;
    private string $tenantName;
    private ?int $eventId;
    private ?int $benefitId;

    public function __construct(
        string $title,
        string $message,
        array $deviceTokens,
        int $notificationId,
        string $tenantName,
        ?int $eventId = null,
        ?int $benefitId = null
    ) {
        $this->title = $title;
        $this->message = $message;
        $this->deviceTokens = $deviceTokens;
        $this->notificationId = $notificationId;
        $this->tenantName = $tenantName;
        $this->eventId = $eventId;
        $this->benefitId = $benefitId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getDeviceTokens(): array
    {
        return $this->deviceTokens;
    }

    public function getNotificationId(): int
    {
        return $this->notificationId;
    }

    public function getTenantName(): string
    {
        return $this->tenantName;
    }

    public function getEventId(): ?int
    {
        return $this->eventId;
    }

    public function getBenefitId(): ?int
    {
        return $this->benefitId;
    }
}
