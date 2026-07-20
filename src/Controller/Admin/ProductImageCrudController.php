<?php

namespace App\Controller\Admin;

use App\Entity\ProductImage;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ProductImageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductImage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('product', 'Produit'),
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
            IntegerField::new('position', 'Ordre d\'affichage'),
        ];
    }
}