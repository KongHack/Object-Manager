<?php
namespace GCWorld\ObjectManager\Tests\Fixtures\AttributeModels\InvalidNameCollision;

use GCWorld\ObjectManager\Attributes\ObjectManagerAttribute;
use GCWorld\ObjectManager\Enums\ObjectManagerMethod;

#[ObjectManagerAttribute(method: ObjectManagerMethod::GetObject)]
class Member
{
}
