<?php
namespace GCWorld\ObjectManager\Tests\Fixtures\AttributeModels;

use GCWorld\ObjectManager\Attributes\ObjectManager;

#[ObjectManager(method: 'getObject', gc: 25)]
class SimpleModel
{
}
