<?php
namespace GCWorld\ObjectManager\Tests\Fixtures\AttributeModels;

use GCWorld\ObjectManager\Attributes\ObjectFactory;
use GCWorld\ObjectManager\Attributes\ObjectManager;

#[ObjectManager(method: 'getFactoryObject')]
class InvalidFactoryModel
{
    #[ObjectFactory]
    protected static function factoryHidden(string $email): self
    {
        return new self();
    }
}
