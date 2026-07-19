<?php

namespace App\Controller\Admin;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use App\Controller\Admin\CategoryCrudController;
use App\Controller\Admin\ProductCrudController;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\Admin\CustomerOrderCrudController;
use App\Controller\Admin\ProductImageCrudController;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private \App\Repository\ProductRepository $productRepository,
        private \App\Repository\CategoryRepository $categoryRepository,
        private \App\Repository\CustomerOrderRepository $orderRepository,
    ) {
    }
    public function index(): Response
    {
        $productCount = $this->productRepository->count([]);
        $categoryCount = $this->categoryRepository->count([]);
        $pendingOrdersCount = $this->orderRepository->count(['status' => 'pending']);
        $totalRevenue = $this->orderRepository->getTotalRevenue();

        return $this->render('admin/dashboard.html.twig', [
            'productCount' => $productCount,
            'categoryCount' => $categoryCount,
            'pendingOrdersCount' => $pendingOrdersCount,
            'totalRevenue' => $totalRevenue,
        ]);
    }
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Mon Projet');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Catégories', 'fa fa-tags');
        yield MenuItem::linkTo(ProductCrudController::class, 'Produits', 'fa fa-box');
        yield MenuItem::linkTo(CustomerOrderCrudController::class, 'Commandes', 'fa fa-receipt');
        yield MenuItem::linkTo(ProductImageCrudController::class, 'Galerie photos', 'fa fa-images');
        yield MenuItem::linkTo(NewsletterSubscriberCrudController::class, 'Newsletter', 'fa fa-envelope');
    }
}
