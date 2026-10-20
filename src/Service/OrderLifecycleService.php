<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\OrderTracking;
use Doctrine\ORM\EntityManagerInterface;

class OrderLifecycleService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function initializeTracking(CustomerOrder $order): void
    {
        $tracking = new OrderTracking();
        $tracking->setCustomerOrder($order);

        if ($order->getPaymentMethod() === CustomerOrder::PAYMENT_METHOD_CASH) {
            $tracking->setStatusStep(OrderTracking::STEP_PENDING_PAYMENT);
            $tracking->setTitle('Commande enregistrée — Paiement à la livraison');
            $tracking->setComment('Votre commande est confirmée. Préparez le montant exact à la remise du colis par le coursier Tiak-Tiak.');
            $order->setStatus(CustomerOrder::STATUS_PENDING);
            $order->setEscrowStatus(CustomerOrder::ESCROW_NONE);
        } else {
            // Wave or Orange Money
            $tracking->setStatusStep(OrderTracking::STEP_PAID_ESCROW);
            $tracking->setTitle('Paiement sécurisé sur le séquestre');
            $tracking->setComment(sprintf(
                'Fonds de %s XOF bloqués en toute sécurité sur le séquestre %s. Le marchand ne sera réglé qu\'après validation de votre colis.',
                number_format($order->getTotal(), 0, ',', ' '),
                $order->getPaymentMethod()
            ));
            $order->setStatus(CustomerOrder::STATUS_PAID_ESCROW);
            $order->setEscrowStatus(CustomerOrder::ESCROW_HELD);
        }

        $order->addOrderTracking($tracking);
        $this->entityManager->persist($tracking);
    }

    public function transitionTo(CustomerOrder $order, string $newStatus, ?string $customComment = null): void
    {
        $order->setStatus($newStatus);

        $tracking = new OrderTracking();
        $tracking->setCustomerOrder($order);

        switch ($newStatus) {
            case CustomerOrder::STATUS_PAID_ESCROW:
                $tracking->setStatusStep(OrderTracking::STEP_PAID_ESCROW);
                $tracking->setTitle('Paiement séquestré validé');
                $tracking->setComment($customComment ?? 'Paiement Mobile Money confirmé avec succès.');
                $order->setEscrowStatus(CustomerOrder::ESCROW_HELD);
                break;

            case CustomerOrder::STATUS_PREPARING:
                $tracking->setStatusStep(OrderTracking::STEP_PREPARING);
                $tracking->setTitle('En préparation en atelier TechLink');
                $tracking->setComment($customComment ?? 'Test des ports, vérification de l\'état de la batterie et emballage renforcé.');
                break;

            case CustomerOrder::STATUS_SHIPPED_TIAK_TIAK:
                $tracking->setStatusStep(OrderTracking::STEP_SHIPPED_TIAK_TIAK);
                $tracking->setTitle('Colis confié au livreur Tiak-Tiak');
                $tracking->setComment($customComment ?? 'Le coursier express est en route. Vous recevrez un appel à l\'approche de votre adresse.');
                break;

            case CustomerOrder::STATUS_DELIVERED:
                $tracking->setStatusStep(OrderTracking::STEP_DELIVERED_CONFIRMED);
                $tracking->setTitle('Colis remis et contrôlé');
                $tracking->setComment($customComment ?? 'Livraison réussie. Fonds libérés et garantie activée.');
                $order->setEscrowStatus(CustomerOrder::ESCROW_RELEASED);
                break;

            case CustomerOrder::STATUS_CANCELLED:
                $tracking->setStatusStep(OrderTracking::STEP_CANCELLED);
                $tracking->setTitle('Commande annulée');
                $tracking->setComment($customComment ?? 'Commande annulée. Remboursement initié le cas échéant.');
                if ($order->getEscrowStatus() === CustomerOrder::ESCROW_HELD) {
                    $order->setEscrowStatus(CustomerOrder::ESCROW_REFUNDED);
                }
                break;

            default:
                $tracking->setStatusStep($newStatus);
                $tracking->setTitle('Mise à jour du statut');
                $tracking->setComment($customComment ?? 'Statut mis à jour vers: ' . $newStatus);
        }

        $order->addOrderTracking($tracking);
        $this->entityManager->persist($tracking);
        $this->entityManager->flush();
    }
}
