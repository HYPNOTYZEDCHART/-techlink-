<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Coupon;
use App\Entity\CustomerOrder;
use App\Entity\OrderTracking;
use App\Entity\PaymentTransaction;
use PHPUnit\Framework\TestCase;

class OrderWorkflowTest extends TestCase
{
    public function testCouponPercentageCalculation(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('TECH10');
        $coupon->setDiscountType(Coupon::TYPE_PERCENTAGE);
        $coupon->setDiscountValue(10);
        $coupon->setMinOrderAmount(20000);
        $coupon->setIsActive(true);

        $this->assertFalse($coupon->isValidForAmount(15000));
        $this->assertTrue($coupon->isValidForAmount(50000));
        $this->assertSame(5000, $coupon->calculateDiscount(50000));
    }

    public function testCouponFixedAmountCalculation(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('DAKAR5000');
        $coupon->setDiscountType(Coupon::TYPE_FIXED_XOF);
        $coupon->setDiscountValue(5000);
        $coupon->setMinOrderAmount(10000);
        $coupon->setIsActive(true);

        $this->assertSame(5000, $coupon->calculateDiscount(30000));
        $this->assertSame(0, $coupon->calculateDiscount(4000)); // Below minOrderAmount (10000)

        // Without minOrderAmount
        $couponNoMin = new Coupon();
        $couponNoMin->setDiscountType(Coupon::TYPE_FIXED_XOF);
        $couponNoMin->setDiscountValue(5000);
        $this->assertSame(4000, $couponNoMin->calculateDiscount(4000)); // Cap at subtotal
    }

    public function testOrderTrackingNumberGeneration(): void
    {
        $order = new CustomerOrder();
        $trackingNumber = $order->generateTrackingNumber();

        $this->assertNotNull($trackingNumber);
        $this->assertStringStartsWith('TL-', $trackingNumber);
        $this->assertSame($trackingNumber, $order->getTrackingNumber());
    }

    public function testPaymentTransactionAttributes(): void
    {
        $order = new CustomerOrder();
        $order->setTotal(125000);
        $order->setDeliveryPhone('+221770000000');

        $tx = new PaymentTransaction();
        $tx->setCustomerOrder($order);
        $tx->setProvider(CustomerOrder::PAYMENT_METHOD_WAVE);
        $tx->setAmount($order->getTotal());
        $tx->setReference('WAV-261020-TEST01');
        $tx->setStatus(PaymentTransaction::STATUS_SUCCESS);

        $this->assertSame('WAVE', $tx->getProvider());
        $this->assertSame(125000, $tx->getAmount());
        $this->assertSame(PaymentTransaction::STATUS_SUCCESS, $tx->getStatus());
    }

    public function testOrderTrackingStepAddition(): void
    {
        $order = new CustomerOrder();
        $tracking = new OrderTracking();
        $tracking->setStatusStep(OrderTracking::STEP_SHIPPED_TIAK_TIAK);
        $tracking->setTitle('En cours de livraison Tiak-Tiak');
        $order->addOrderTracking($tracking);

        $this->assertCount(1, $order->getOrderTrackings());
        $this->assertSame(OrderTracking::STEP_SHIPPED_TIAK_TIAK, $order->getOrderTrackings()->first()->getStatusStep());
    }
}
