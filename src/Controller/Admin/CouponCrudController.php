<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Coupon;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CouponCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Coupon::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('code', 'Code Promo')->setHelp('Ex: DAKAR10, PROMO2026'),
            ChoiceField::new('discountType', 'Type de réduction')->setChoices([
                'Pourcentage (%)' => Coupon::TYPE_PERCENTAGE,
                'Montant fixe (XOF)' => Coupon::TYPE_FIXED_XOF,
            ]),
            IntegerField::new('discountValue', 'Valeur')->setHelp('Ex: 10 pour 10%, ou 5000 pour 5000 XOF'),
            IntegerField::new('minOrderAmount', 'Montant minimum commande (XOF)'),
            IntegerField::new('maxUses', 'Nombre d\'utilisations max')->setHelp('Laisser vide pour illimité'),
            IntegerField::new('usedCount', 'Utilisations actuelles')->hideOnForm(),
            BooleanField::new('isActive', 'Actif'),
            DateTimeField::new('expiresAt', 'Date d\'expiration'),
            DateTimeField::new('createdAt', 'Créé le')->hideOnForm(),
        ];
    }
}
