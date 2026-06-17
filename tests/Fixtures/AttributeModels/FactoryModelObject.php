<?php
namespace GCWorld\ObjectManager\Tests\Fixtures\AttributeModels;

use GCWorld\ObjectManager\Attributes\ObjectFactoryAttribute;
use GCWorld\ObjectManager\Attributes\ObjectManagerAttribute;
use GCWorld\ObjectManager\Enums\ObjectManagerMethod;

#[ObjectManagerAttribute(method: ObjectManagerMethod::GetFactoryModelObject)]
class FactoryModelObject
{
    #[ObjectFactoryAttribute]
    public static function factoryLookup(string $email): self
    {
        return new self();
    }
}
