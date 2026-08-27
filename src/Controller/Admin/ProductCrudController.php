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
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $manageGallery = Action::new('manageGallery', 'Photos')
            ->linkToUrl(fn ($entity) => $this->container->get('router')->generate('admin_gallery_upload', ['id' => $entity->getId()]));

        $duplicate = Action::new('duplicate', 'Dupliquer')
            ->linkToCrudAction('duplicateProduct');

        return $actions
            ->add(Crud::PAGE_INDEX, $manageGallery)
            ->add(Crud::PAGE_INDEX, $duplicate);
    }

    #[AdminRoute('/duplicate/{entityId}', 'duplicate')]
    public function duplicateProduct(AdminContext $context, EntityManagerInterface $em): Response
    {
        $original = $context->getEntity()->getInstance();

        $copy = clone $original;
        $copy->setName($original->getName() . ' (copie)');
        $copy->setSlug($original->getSlug() . '-copie-' . uniqid());

        $em->persist($copy);
        $em->flush();

        $this->addFlash('success', 'Produit dupliqué avec succès.');

        $adminUrlGenerator = $this->container->get(\EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator::class);
$url = $adminUrlGenerator->setController(self::class)->setAction('index')->generateUrl();

return $this->redirect($url);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name', 'Nom'),
            TextField::new('slug'),
            TextareaField::new('description'),
            IntegerField::new('price', 'Prix (en XOF)'),
            IntegerField::new('oldPrice', 'Ancien prix (en XOF, optionnel)')->hideOnIndex(),
            TextField::new('colors', 'Couleurs (séparées par des virgules)')->hideOnIndex(),
            IntegerField::new('stock')
                ->formatValue(function ($value) {
                    if ($value <= 0) {
                        return '<span style="color: #ef4444; font-weight: 600;">' . $value . ' — Rupture</span>';
                    }
                    if ($value <= 5) {
                        return '<span style="color: #f97316; font-weight: 600;">' . $value . ' — Stock faible</span>';
                    }
                    return $value;
                }),
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
            TextareaField::new('specificationsRaw', 'Spécifications techniques')
                ->hideOnIndex()
                ->setNumOfRows(8)
                ->setHelp(
                    'Une spécification par ligne, format <strong>Clé: Valeur</strong>.<br>' .
                    'Exemples :<br>' .
                    '<code>Processeur: Intel Core i7-1355U</code><br>' .
                    '<code>RAM: 16 Go DDR5</code><br>' .
                    '<code>Stockage: 512 Go SSD NVMe</code><br>' .
                    '<code>Écran: 15.6" Full HD 144Hz</code>'
                ),
        ];
    }
}