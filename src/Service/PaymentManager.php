<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\PaymentTransaction;
use Doctrine\ORM\EntityManagerInterface;

class PaymentManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function initiateTransaction(
        CustomerOrder $order,
        string $provider,
        ?string $phoneNumber = null
    ): PaymentTransaction {
        $prefix = match ($provider) {
            CustomerOrder::PAYMENT_METHOD_WAVE => 'WAV',
            CustomerOrder::PAYMENT_METHOD_ORANGE_MONEY => 'OM',
            default => 'COD',
        };

        $reference = sprintf('%s-%s-%s', $prefix, date('ymd'), strtoupper(bin2hex(random_bytes(3))));

        $transaction = new PaymentTransaction();
        $transaction->setCustomerOrder($order);
        $transaction->setProvider($provider);
        $transaction->setAmount($order->getTotal());
        $transaction->setReference($reference);
        $transaction->setPhoneNumber($phoneNumber ?? $order->getDeliveryPhone());

        if ($provider === CustomerOrder::PAYMENT_METHOD_CASH) {
            $transaction->setStatus(PaymentTransaction::STATUS_PENDING);
        } else {
            // Wave / Orange Money simulated immediate escrow confirmation
            $transaction->setStatus(PaymentTransaction::STATUS_SUCCESS);
            $transaction->setMetadata([
                'escrow_account' => 'OPENBOUTIK_ESCROW_SN',
                'gateway' => $provider,
                'verified_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'channel' => 'MOBILE_MONEY_SN',
            ]);
        }

        $order->addPaymentTransaction($transaction);
        $this->entityManager->persist($transaction);

        return $transaction;
    }
}
