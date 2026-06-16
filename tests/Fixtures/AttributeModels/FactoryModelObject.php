<?php
namespace GCWorld\ObjectManager\Tests\Fixtures\AttributeModels;

use GCWorld\ObjectManager\Attributes\ObjectFactory;
use GCWorld\ObjectManager\Attributes\ObjectManager;

#[ObjectManager(method: 'getFactoryModelObject')]
class FactoryModelObject
{
    #[ObjectFactory]
    public static function factoryLookup(string $email): self
    {
        return new self();
    }
}
