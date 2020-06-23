<?php

namespace App\Controller\Admin;

use App\Entity\Channel;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class ChannelController extends AbstractCrudController
{
    /**
     * {@inheritDoc}
     */
    public static function getEntityFqcn(): string
    {
        return Channel::class;
    }

    /**
     * {@inheritDoc}
     */
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Channel %entity_label_singular%')
            ->setSearchFields(['id', 'link', 'name', 'createdAt'])
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('id', 'ID')->hideOnForm();
        yield UrlField::new('link');
        yield TextField::new('name');
        yield DateTimeField::new('createdAt');
    }
}
