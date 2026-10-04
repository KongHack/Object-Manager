<?php
namespace GCWorld\ObjectManager\Tests\Fixtures\AttributeModels\NameCollision\ADR;

use GCWorld\ObjectManager\Attributes\ObjectManagerAttribute;
use GCWorld\ObjectManager\Enums\ObjectManagerMethod;

#[ObjectManagerAttribute(method: ObjectManagerMethod::GetObject, name: 'ADRMember')]
class Member
{
}
