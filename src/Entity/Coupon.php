<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CouponRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CouponRepository::class)]
#[ORM\Table(name: 'coupon')]
#[ORM\Index(name: 'idx_coupon_code', columns: ['code'])]
class Coupon
{
    public const TYPE_PERCENTAGE = 'PERCENTAGE';
    public const TYPE_FIXED_XOF = 'FIXED_XOF';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 20)]
    private string $discountType = self::TYPE_PERCENTAGE;

    #[ORM\Column]
    private int $discountValue = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $minOrderAmount = 0;

    #[ORM\Column(nullable: true)]
    private ?int $maxUses = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $usedCount = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper(trim($code));
        return $this;
    }

    public function getDiscountType(): string
    {
        return $this->discountType;
    }

    public function setDiscountType(string $discountType): static
    {
        $this->discountType = $discountType;
        return $this;
    }

    public function getDiscountValue(): int
    {
        return $this->discountValue;
    }

    public function setDiscountValue(int $discountValue): static
    {
        $this->discountValue = $discountValue;
        return $this;
    }

    public function getMinOrderAmount(): int
    {
        return $this->minOrderAmount;
    }

    public function setMinOrderAmount(int $minOrderAmount): static
    {
        $this->minOrderAmount = $minOrderAmount;
        return $this;
    }

    public function getMaxUses(): ?int
    {
        return $this->maxUses;
    }

    public function setMaxUses(?int $maxUses): static
    {
        $this->maxUses = $maxUses;
        return $this;
    }

    public function getUsedCount(): int
    {
        return $this->usedCount;
    }

    public function setUsedCount(int $usedCount): static
    {
        $this->usedCount = $usedCount;
        return $this;
    }

    public function incrementUsedCount(): static
    {
        $this->usedCount++;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isValidForAmount(int $subtotal): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if ($this->expiresAt && $this->expiresAt < new \DateTimeImmutable()) {
            return false;
        }

        if ($this->maxUses !== null && $this->usedCount >= $this->maxUses) {
            return false;
        }

        if ($subtotal < $this->minOrderAmount) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(int $subtotal): int
    {
        if (!$this->isValidForAmount($subtotal)) {
            return 0;
        }

        if ($this->discountType === self::TYPE_PERCENTAGE) {
            $discount = (int) round(($subtotal * $this->discountValue) / 100);
            return min($discount, $subtotal);
        }

        return min($this->discountValue, $subtotal);
    }
}
