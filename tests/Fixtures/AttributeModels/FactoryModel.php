<?php
namespace GCWorld\ObjectManager\Tests\Fixtures\AttributeModels;

use GCWorld\ObjectManager\Attributes\ObjectFactory;
use GCWorld\ObjectManager\Attributes\ObjectManager;
use GCWorld\ObjectManager\Enums\ObjectManagerMethod;

#[ObjectManager(method: ObjectManagerMethod::GetFactoryObject, gc: 10)]
class FactoryModel
{
    public const CLASS_PRIMARY = 'uuid';

    #[ObjectFactory]
    public static function factoryByUuid(string $uuid): self
    {
        return new self();
    }

    #[ObjectFactory]
    public static function factoryLookup(string $email, string $name): self
    {
        return new self();
    }
}
