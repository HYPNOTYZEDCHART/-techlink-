<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name', 'Nom'),
            TextField::new('slug'),
            TextareaField::new('description'),
            IntegerField::new('price', 'Prix (centimes)'),
            IntegerField::new('oldPrice', 'Ancien prix (centimes, optionnel)')->hideOnIndex(),
            TextField::new('colors', 'Couleurs (séparées par des virgules)')->hideOnIndex(),
            IntegerField::new('stock'),
            TextField::new('brand', 'Marque'),
            TextField::new('sku', 'SKU'),
            TextField::new('imageFile', 'Image')
    ->setFormType(VichImageType::class)
    ->setFormTypeOptions([
        'allow_delete' => true,
        'constraints' => [
            new \Symfony\Component\Validator\Constraints\File(
                maxSize: '5M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                mimeTypesMessage: 'Merci d\'uploader une image valide (JPEG, PNG ou WebP)',
                maxSizeMessage: 'L\'image ne doit pas dépasser {{ limit }} {{ suffix }}',
            ),
        ],
    ])
    ->onlyOnForms(),
            BooleanField::new('isActive', 'Actif'),
            AssociationField::new('category', 'Catégorie'),
        ];
    }
}