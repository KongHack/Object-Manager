<?php
namespace GCWorld\ObjectManager\Tests\Fixtures\AttributeModels;

use GCWorld\ObjectManager\Attributes\ObjectFactory;
use GCWorld\ObjectManager\Attributes\ObjectManager;
use GCWorld\ObjectManager\Enums\ObjectManagerMethod;

#[ObjectManager(method: ObjectManagerMethod::GetFactoryObject)]
class InvalidFactoryModel
{
    #[ObjectFactory]
    protected static function factoryHidden(string $email): self
    {
        return new self();
    }
}
