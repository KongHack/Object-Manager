<?php
namespace GCWorld\ObjectManager\Attributes;

use Attribute;
use GCWorld\ObjectManager\Enums\ObjectManagerMethod;

#[Attribute(Attribute::TARGET_CLASS)]
class ObjectManagerAttribute
{
    public readonly ObjectManagerMethod $method;
    public readonly ?string $name;
    public readonly ?string $namespace;
    public readonly int $gc;

    public function __construct(
        ObjectManagerMethod $method,
        ?string $name = null,
        ?string $namespace = null,
        int $gc = 0
    )
    {
        $this->method    = $method;
        $this->name      = $name;
        $this->namespace = $namespace;
        $this->gc        = abs($gc);
    }
}
