<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CustomerOrder;
use App\Repository\CustomerOrderRepository;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;

class CustomerOrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CustomerOrder::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices([
                'En attente' => CustomerOrder::STATUS_PENDING,
                'Séquestre validé' => CustomerOrder::STATUS_PAID_ESCROW,
                'En préparation' => CustomerOrder::STATUS_PREPARING,
                'Expédiée Tiak-Tiak' => CustomerOrder::STATUS_SHIPPED_TIAK_TIAK,
                'Livrée' => CustomerOrder::STATUS_DELIVERED,
                'Annulée' => CustomerOrder::STATUS_CANCELLED,
            ]))
            ->add(ChoiceFilter::new('paymentMethod', 'Moyen de paiement')->setChoices([
                'Wave' => CustomerOrder::PAYMENT_METHOD_WAVE,
                'Orange Money' => CustomerOrder::PAYMENT_METHOD_ORANGE_MONEY,
                'Paiement à la livraison' => CustomerOrder::PAYMENT_METHOD_CASH,
            ]))
            ->add(DateTimeFilter::new('createdAt', 'Date'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('trackingNumber', 'N° Suivi')->hideOnForm(),
            AssociationField::new('user', 'Client')->hideOnForm(),
            TextField::new('guestEmail', 'Email (Invité)')->hideOnForm(),
            TextField::new('guestFirstName', 'Prénom (Invité)')->hideOnForm(),
            TextField::new('guestLastName', 'Nom (Invité)')->hideOnForm(),
            ChoiceField::new('status', 'Statut')->setChoices([
                'En attente' => CustomerOrder::STATUS_PENDING,
                'Séquestre validé' => CustomerOrder::STATUS_PAID_ESCROW,
                'En préparation' => CustomerOrder::STATUS_PREPARING,
                'Expédiée Tiak-Tiak' => CustomerOrder::STATUS_SHIPPED_TIAK_TIAK,
                'Livrée' => CustomerOrder::STATUS_DELIVERED,
                'Annulée' => CustomerOrder::STATUS_CANCELLED,
            ]),
            ChoiceField::new('paymentMethod', 'Paiement')->setChoices([
                'Wave' => CustomerOrder::PAYMENT_METHOD_WAVE,
                'Orange Money' => CustomerOrder::PAYMENT_METHOD_ORANGE_MONEY,
                'À la livraison' => CustomerOrder::PAYMENT_METHOD_CASH,
            ]),
            ChoiceField::new('deliveryMethod', 'Mode livraison')->setChoices([
                'Coursier Tiak-Tiak' => CustomerOrder::DELIVERY_METHOD_TIAK_TIAK,
                'Retrait Boutique' => CustomerOrder::DELIVERY_METHOD_PICKUP,
            ]),
            IntegerField::new('total', 'Total (XOF)')->hideOnForm(),
            IntegerField::new('deliveryFee', 'Frais livraison (XOF)')->hideOnForm(),
            IntegerField::new('discountAmount', 'Réduction (XOF)')->hideOnForm(),
            TextField::new('couponCode', 'Code Promo')->hideOnForm(),
            TextField::new('deliveryPhone', 'Téléphone'),
            TextareaField::new('deliveryAddress', 'Adresse de livraison'),
            TextareaField::new('notes', 'Instructions coursier')->hideOnIndex(),
            DateTimeField::new('createdAt', 'Date')->hideOnForm(),
            TextareaField::new('orderItemsSummary', 'Articles commandés')->onlyOnDetail(),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setEntityLabelInSingular('Commande Client')
            ->setEntityLabelInPlural('Commandes Clients');
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportCsv = Action::new('exportCsv', 'Exporter CSV')
            ->linkToCrudAction('exportCsv')
            ->createAsGlobalAction();

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $exportCsv);
    }

    #[AdminRoute('/export-csv', 'export_csv')]
    public function exportCsv(CustomerOrderRepository $repository): Response
    {
        $orders = $repository->findAll();

        $response = new StreamedResponse(function () use ($orders) {
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, ['ID', 'N° Suivi', 'Client', 'Statut', 'Paiement', 'Total (XOF)', 'Frais (XOF)', 'Date']);

            foreach ($orders as $order) {
                $client = $order->getUser() ? $order->getUser()->getEmail() : $order->getGuestEmail() . ' (Invité)';
                fputcsv($handle, [
                    $order->getId(),
                    $order->getTrackingNumber() ?? '',
                    $client,
                    $order->getStatus(),
                    $order->getPaymentMethod(),
                    $order->getTotal(),
                    $order->getDeliveryFee(),
                    $order->getCreatedAt()?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="commandes-techlink-' . date('Y-m-d') . '.csv"');

        return $response;
    }
}