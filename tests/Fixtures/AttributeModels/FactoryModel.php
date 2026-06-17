<?php
namespace GCWorld\ObjectManager\Tests\Fixtures\AttributeModels;

use GCWorld\ObjectManager\Attributes\ObjectFactoryAttribute;
use GCWorld\ObjectManager\Attributes\ObjectManagerAttribute;
use GCWorld\ObjectManager\Enums\ObjectManagerMethod;

#[ObjectManagerAttribute(method: ObjectManagerMethod::GetFactoryObject, gc: 10)]
class FactoryModel
{
    public const CLASS_PRIMARY = 'uuid';

    #[ObjectFactoryAttribute]
    public static function factoryByUuid(string $uuid): self
    {
        return new self();
    }

    #[ObjectFactoryAttribute]
    public static function factoryLookup(string $email, string $name): self
    {
        return new self();
    }
}
