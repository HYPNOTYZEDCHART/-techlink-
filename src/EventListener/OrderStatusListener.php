<?php

namespace App\EventListener;

use App\Entity\CustomerOrder;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

#[AsDoctrineListener(event: Events::preUpdate)]
class OrderStatusListener
{
    public function __construct(
        private MailerInterface $mailer,
        private string $senderEmail,
    ) {
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof CustomerOrder) {
            return;
        }

        if (!$args->hasChangedField('status')) {
            return;
        }

        $newStatus = $args->getNewValue('status');

        $this->sendStatusEmail($entity, $newStatus);
    }

    private function sendStatusEmail(CustomerOrder $order, string $newStatus): void
    {
        $to = $order->getUser() ? $order->getUser()->getEmail() : $order->getGuestEmail();
        if (!$to) {
            return;
        }

        $labels = [
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée',
        ];

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'TechLink'))
            ->to((string) $to)
            ->subject('Ta commande n°' . $order->getId() . ' est ' . strtolower($labels[$newStatus] ?? $newStatus))
            ->htmlTemplate('emails/order_status_update.html.twig')
            ->context([
                'order' => $order,
                'statusLabel' => $labels[$newStatus] ?? $newStatus,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // On ne bloque pas la mise à jour du statut si l'email échoue
        }
    }
}