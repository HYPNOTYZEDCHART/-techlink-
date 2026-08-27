<?php

namespace App\Controller\Admin;

use App\Entity\ProductColor;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProductColorCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductColor::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('product', 'Produit'),
            TextField::new('name', 'Nom de la couleur'),
            ColorField::new('hexCode', 'Code couleur'),
            \EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField::new('stock', 'En stock'),
        ];
    }
}