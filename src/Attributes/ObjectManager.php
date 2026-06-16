<?php
namespace GCWorld\ObjectManager\Attributes;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
class ObjectManager
{
    private const VALID_METHODS = [
        'getObject',
        'getModel',
        'getFactoryObject',
        'getFactoryModelObject',
        'getMultiObject',
    ];

    public readonly string $method;
    public readonly ?string $name;
    public readonly ?string $namespace;
    public readonly int $gc;

    public function __construct(string $method, ?string $name = null, ?string $namespace = null, int $gc = 0)
    {
        if (!in_array($method, self::VALID_METHODS, true)) {
            throw new InvalidArgumentException('Invalid ObjectManager method: '.$method);
        }

        $this->method    = $method;
        $this->name      = $name;
        $this->namespace = $namespace;
        $this->gc        = abs($gc);
    }
}
