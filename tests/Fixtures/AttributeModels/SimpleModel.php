<?php
namespace GCWorld\ObjectManager\Tests\Fixtures\AttributeModels;

use GCWorld\ObjectManager\Attributes\ObjectManager;
use GCWorld\ObjectManager\Enums\ObjectManagerMethod;

#[ObjectManager(method: ObjectManagerMethod::GetObject, gc: 25)]
class SimpleModel
{
}
