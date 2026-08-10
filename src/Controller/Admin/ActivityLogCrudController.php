<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ActivityLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ActivityLog::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('user', 'Utilisateur'),
            ChoiceField::new('action', 'Action')->setChoices([
                'Création' => 'created',
                'Modification' => 'updated',
                'Suppression' => 'deleted',
            ]),
            TextField::new('entityType', 'Type'),
            IntegerField::new('entityId', 'ID concerné'),
            DateTimeField::new('createdAt', 'Date'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
{
    return $crud
        ->setDefaultSort(['createdAt' => 'DESC'])
        ->setEntityLabelInSingular('Log d\'activité')
        ->setEntityLabelInPlural('Logs d\'activité');
}
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }
}