<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderTrackingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderTrackingRepository::class)]
#[ORM\Table(name: 'order_tracking')]
#[ORM\Index(name: 'idx_order_tracking_step', columns: ['status_step'])]
#[ORM\Index(name: 'idx_order_tracking_timestamp', columns: ['timestamp'])]
class OrderTracking
{
    public const STEP_PENDING_PAYMENT = 'PENDING_PAYMENT';
    public const STEP_PAID_ESCROW = 'PAID_ESCROW';
    public const STEP_PREPARING = 'PREPARING';
    public const STEP_SHIPPED_TIAK_TIAK = 'SHIPPED_TIAK_TIAK';
    public const STEP_DELIVERED_CONFIRMED = 'DELIVERED_CONFIRMED';
    public const STEP_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orderTrackings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CustomerOrder $customerOrder = null;

    #[ORM\Column(length: 50, name: 'status_step')]
    private string $statusStep = self::STEP_PENDING_PAYMENT;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private \DateTimeImmutable $timestamp;

    public function __construct()
    {
        $this->timestamp = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerOrder(): ?CustomerOrder
    {
        return $this->customerOrder;
    }

    public function setCustomerOrder(?CustomerOrder $customerOrder): static
    {
        $this->customerOrder = $customerOrder;
        return $this;
    }

    public function getStatusStep(): string
    {
        return $this->statusStep;
    }

    public function setStatusStep(string $statusStep): static
    {
        $this->statusStep = $statusStep;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeImmutable $timestamp): static
    {
        $this->timestamp = $timestamp;
        return $this;
    }
}
