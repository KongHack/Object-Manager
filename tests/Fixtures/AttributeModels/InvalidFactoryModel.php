<?php
namespace GCWorld\ObjectManager\Tests\Fixtures\AttributeModels;

use GCWorld\ObjectManager\Attributes\ObjectFactoryAttribute;
use GCWorld\ObjectManager\Attributes\ObjectManagerAttribute;
use GCWorld\ObjectManager\Enums\ObjectManagerMethod;

#[ObjectManagerAttribute(method: ObjectManagerMethod::GetFactoryObject)]
class InvalidFactoryModel
{
    #[ObjectFactoryAttribute]
    protected static function factoryHidden(string $email): self
    {
        return new self();
    }
}
