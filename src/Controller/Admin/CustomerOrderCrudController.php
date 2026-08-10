<?php

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
use Symfony\Component\Routing\Attribute\Route;
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
                'En attente' => 'pending',
                'Confirmée' => 'confirmed',
                'Expédiée' => 'shipped',
                'Livrée' => 'delivered',
                'Annulée' => 'cancelled',
            ]))
            ->add(DateTimeFilter::new('createdAt', 'Date'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('user', 'Client')->hideOnForm(),
            ChoiceField::new('status', 'Statut')->setChoices([
                'En attente' => 'pending',
                'Confirmée' => 'confirmed',
                'Expédiée' => 'shipped',
                'Livrée' => 'delivered',
                'Annulée' => 'cancelled',
            ]),
            IntegerField::new('total', 'Total (centimes)')->hideOnForm(),
            TextField::new('deliveryPhone', 'Téléphone'),
            TextareaField::new('deliveryAddress', 'Adresse de livraison'),
            DateTimeField::new('createdAt', 'Date')->hideOnForm(),
            TextareaField::new('orderItemsSummary', 'Articles commandés')->onlyOnDetail(),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['createdAt' => 'DESC']);
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
            fputcsv($handle, ['ID', 'Client', 'Statut', 'Total (XOF)', 'Date']);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->getId(),
                    $order->getUser()->getEmail(),
                    $order->getStatus(),
                    $order->getTotal() / 100,
                    $order->getCreatedAt()->format('d/m/Y H:i'),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="commandes.csv"');

        return $response;
    }
}