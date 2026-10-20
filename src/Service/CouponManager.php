<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coupon;
use App\Repository\CouponRepository;
use Doctrine\ORM\EntityManagerInterface;

class CouponManager
{
    public function __construct(
        private readonly CouponRepository $couponRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{valid: bool, coupon: ?Coupon, discount: int, message: string}
     */
    public function applyCoupon(?string $code, int $subtotal): array
    {
        if (!$code) {
            return [
                'valid' => false,
                'coupon' => null,
                'discount' => 0,
                'message' => 'Aucun code fourni.',
            ];
        }

        $coupon = $this->couponRepository->findValidByCode($code);

        if (!$coupon) {
            return [
                'valid' => false,
                'coupon' => null,
                'discount' => 0,
                'message' => 'Code promo invalide ou expiré.',
            ];
        }

        if (!$coupon->isValidForAmount($subtotal)) {
            $msg = 'Conditions non remplies pour ce code promo.';
            if ($subtotal < $coupon->getMinOrderAmount()) {
                $msg = sprintf(
                    'Le montant minimum pour ce code est de %s XOF.',
                    number_format($coupon->getMinOrderAmount(), 0, ',', ' ')
                );
            }
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount' => 0,
                'message' => $msg,
            ];
        }

        $discount = $coupon->calculateDiscount($subtotal);

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'message' => sprintf('Réduction de %s XOF appliquée !', number_format($discount, 0, ',', ' ')),
        ];
    }

    public function recordUsage(Coupon $coupon): void
    {
        $coupon->incrementUsedCount();
        $this->entityManager->persist($coupon);
        $this->entityManager->flush();
    }
}
