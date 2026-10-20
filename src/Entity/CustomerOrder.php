<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CustomerOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerOrderRepository::class)]
#[ORM\Table(name: 'customer_order')]
#[ORM\Index(name: 'idx_order_tracking_number', columns: ['tracking_number'])]
#[ORM\Index(name: 'idx_order_status', columns: ['status'])]
class CustomerOrder
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID_ESCROW = 'paid_escrow';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_SHIPPED_TIAK_TIAK = 'shipped_tiak_tiak';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const ESCROW_NONE = 'NONE';
    public const ESCROW_HELD = 'HELD_IN_ESCROW';
    public const ESCROW_RELEASED = 'RELEASED_TO_SHOP';
    public const ESCROW_REFUNDED = 'REFUNDED_TO_CLIENT';

    public const DELIVERY_METHOD_TIAK_TIAK = 'TIAK_TIAK';
    public const DELIVERY_METHOD_PICKUP = 'PICKUP';

    public const PAYMENT_METHOD_WAVE = 'WAVE';
    public const PAYMENT_METHOD_ORANGE_MONEY = 'ORANGE_MONEY';
    public const PAYMENT_METHOD_CASH = 'CASH_ON_DELIVERY';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private ?int $total = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $deliveryAddress = null;

    #[ORM\Column(length: 20)]
    private ?string $deliveryPhone = null;

    #[ORM\ManyToOne(inversedBy: 'customerOrders')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $guestEmail = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $guestFirstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $guestLastName = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $deliveryFee = 0;

    #[ORM\Column(length: 30, options: ['default' => self::PAYMENT_METHOD_CASH])]
    private string $paymentMethod = self::PAYMENT_METHOD_CASH;

    #[ORM\Column(length: 30, options: ['default' => self::DELIVERY_METHOD_TIAK_TIAK])]
    private string $deliveryMethod = self::DELIVERY_METHOD_TIAK_TIAK;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $couponCode = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $discountAmount = 0;

    #[ORM\Column(length: 30, options: ['default' => self::ESCROW_NONE])]
    private string $escrowStatus = self::ESCROW_NONE;

    #[ORM\Column(length: 50, unique: true, nullable: true, name: 'tracking_number')]
    private ?string $trackingNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'customerOrder', orphanRemoval: true)]
    private Collection $orderItems;

    /**
     * @var Collection<int, PaymentTransaction>
     */
    #[ORM\OneToMany(targetEntity: PaymentTransaction::class, mappedBy: 'customerOrder', orphanRemoval: true)]
    private Collection $paymentTransactions;

    /**
     * @var Collection<int, OrderTracking>
     */
    #[ORM\OneToMany(targetEntity: OrderTracking::class, mappedBy: 'customerOrder', orphanRemoval: true)]
    #[ORM\OrderBy(['timestamp' => 'ASC'])]
    private Collection $orderTrackings;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
        $this->paymentTransactions = new ArrayCollection();
        $this->orderTrackings = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(int $total): static
    {
        $this->total = $total;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(string $deliveryAddress): static
    {
        $this->deliveryAddress = $deliveryAddress;
        return $this;
    }

    public function getDeliveryPhone(): ?string
    {
        return $this->deliveryPhone;
    }

    public function setDeliveryPhone(string $deliveryPhone): static
    {
        $this->deliveryPhone = $deliveryPhone;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getGuestEmail(): ?string
    {
        return $this->guestEmail;
    }

    public function setGuestEmail(?string $guestEmail): static
    {
        $this->guestEmail = $guestEmail;
        return $this;
    }

    public function getGuestFirstName(): ?string
    {
        return $this->guestFirstName;
    }

    public function setGuestFirstName(?string $guestFirstName): static
    {
        $this->guestFirstName = $guestFirstName;
        return $this;
    }

    public function getGuestLastName(): ?string
    {
        return $this->guestLastName;
    }

    public function setGuestLastName(?string $guestLastName): static
    {
        $this->guestLastName = $guestLastName;
        return $this;
    }

    public function getDeliveryFee(): ?int
    {
        return $this->deliveryFee;
    }

    public function setDeliveryFee(int $deliveryFee): static
    {
        $this->deliveryFee = $deliveryFee;
        return $this;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getDeliveryMethod(): string
    {
        return $this->deliveryMethod;
    }

    public function setDeliveryMethod(string $deliveryMethod): static
    {
        $this->deliveryMethod = $deliveryMethod;
        return $this;
    }

    public function getCouponCode(): ?string
    {
        return $this->couponCode;
    }

    public function setCouponCode(?string $couponCode): static
    {
        $this->couponCode = $couponCode ? strtoupper(trim($couponCode)) : null;
        return $this;
    }

    public function getDiscountAmount(): int
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(int $discountAmount): static
    {
        $this->discountAmount = $discountAmount;
        return $this;
    }

    public function getEscrowStatus(): string
    {
        return $this->escrowStatus;
    }

    public function setEscrowStatus(string $escrowStatus): static
    {
        $this->escrowStatus = $escrowStatus;
        return $this;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): static
    {
        $this->trackingNumber = $trackingNumber;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setCustomerOrder($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            if ($orderItem->getCustomerOrder() === $this) {
                $orderItem->setCustomerOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PaymentTransaction>
     */
    public function getPaymentTransactions(): Collection
    {
        return $this->paymentTransactions;
    }

    public function addPaymentTransaction(PaymentTransaction $transaction): static
    {
        if (!$this->paymentTransactions->contains($transaction)) {
            $this->paymentTransactions->add($transaction);
            $transaction->setCustomerOrder($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, OrderTracking>
     */
    public function getOrderTrackings(): Collection
    {
        return $this->orderTrackings;
    }

    public function addOrderTracking(OrderTracking $tracking): static
    {
        if (!$this->orderTrackings->contains($tracking)) {
            $this->orderTrackings->add($tracking);
            $tracking->setCustomerOrder($this);
        }

        return $this;
    }

    public function getOrderItemsSummary(): string
    {
        $lines = [];
        foreach ($this->getOrderItems() as $item) {
            $line = $item->getProduct()->getName() . ' × ' . $item->getQuantity();
            if ($item->getSelectedColor()) {
                $line .= ' (' . $item->getSelectedColor() . ')';
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    public function generateTrackingNumber(): string
    {
        if (!$this->trackingNumber) {
            $this->trackingNumber = 'TL-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        }
        return $this->trackingNumber;
    }
}
