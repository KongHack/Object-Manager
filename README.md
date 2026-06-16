# GCWorld Object Manager

A simple object manager that maintains objects in memory

#### Version
2.8.7



### ObjectManager Attributes

Use PHP attributes instead of docblocks to mark classes for manager generation.

#### Class Attribute

```php
use GCWorld\ObjectManager\Attributes\ObjectManager;

#[ObjectManager(method: 'getObject', gc: 100)]
class User
{
}
```

Available `ObjectManager` arguments:

| Argument     | Required | Notes                                                                 |
|--------------|----------|-----------------------------------------------------------------------|
| `method`     | yes      | `getObject`, `getModel`, `getFactoryObject`, `getFactoryModelObject`, `getMultiObject` |
| `name`       | no       | Getter suffix override. Defaults to the class short name.             |
| `namespace`  | no       | Namespace override used in generated manager methods.                 |
| `gc`         | no       | Automated garbage-collection limit. `0` disables it.                 |

#### Factory Methods

Factory-backed classes use a class attribute plus one or more attributed public static methods.

```php
use GCWorld\ObjectManager\Attributes\ObjectFactory;
use GCWorld\ObjectManager\Attributes\ObjectManager;

#[ObjectManager(method: 'getFactoryObject')]
class User
{
    public const CLASS_PRIMARY = 'uuid';

    #[ObjectFactory]
    public static function factoryByUuid(string $uuid): self
    {
        // ...
    }

    #[ObjectFactory]
    public static function factoryLookup(string $email, string $name): self
    {
        // ...
    }
}
```

The generator reflects the attributed static method parameters directly. There is no separate attribute syntax for ordered factory args.
