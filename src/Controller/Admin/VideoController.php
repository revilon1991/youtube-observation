<?php

namespace App\Controller\Admin;

use App\Entity\Video;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class VideoController extends AbstractCrudController
{
    /**
     * @return string
     */
    public static function getEntityFqcn(): string
    {
        return Video::class;
    }

    /**
     * {@inheritDoc}
     */
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Video %entity_label_singular%')
            ->setSearchFields(['id', 'externalId', 'publicUrl', 'channel', 'createdAt'])
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('id', 'ID')->hideOnForm();
        yield TextField::new('externalId');
        yield UrlField::new('publicUrl');
        yield TextField::new('channel.name');
        yield DateTimeField::new('createdAt');
    }
}
