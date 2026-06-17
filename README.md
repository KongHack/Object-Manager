# GCWorld Object Manager

A simple object manager that maintains objects in memory

#### Version
2.8.7

### ObjectManager Attributes

Use PHP attributes instead of docblocks to mark classes for manager generation.

#### Class Attribute

```php
use GCWorld\ObjectManager\Attributes\ObjectManager;
use GCWorld\ObjectManager\Enums\ObjectManagerMethod;

#[ObjectManager(method: ObjectManagerMethod::GetObject, gc: 100)]
class User
{
}
```

Available `ObjectManager` arguments:

| Argument     | Required | Notes                                                                 |
|--------------|----------|-----------------------------------------------------------------------|
| `method`     | yes      | `ObjectManagerMethod::GetObject`, `::GetModel`, `::GetFactoryObject`, `::GetFactoryModelObject` |
| `name`       | no       | Getter suffix override. Defaults to the class short name.             |
| `namespace`  | no       | Namespace override used in generated manager methods.                 |
| `gc`         | no       | Automated garbage-collection limit. `0` disables it.                 |

These enum cases map to the generated manager behaviors `getObject`, `getModel`, `getFactoryObject`, and `getFactoryModelObject`.

#### Factory Methods

Factory-backed classes use a class attribute plus one or more attributed public static methods.

```php
use GCWorld\ObjectManager\Attributes\ObjectFactory;
use GCWorld\ObjectManager\Attributes\ObjectManager;
use GCWorld\ObjectManager\Enums\ObjectManagerMethod;

#[ObjectManager(method: ObjectManagerMethod::GetFactoryObject)]
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

### Generating The Manager

Generate the manager after your models are autoloadable and your scan path has been added:

```php
use GCWorld\ObjectManager\Generator;

$generator = new Generator();
$generator->addPath(__DIR__.'/src/Model');
$generator->generate();
```

This writes the generated manager class to `src/Generated/GeneratedManager.php`.

### Testing

The repository includes a PHPUnit suite that exercises the generator against fixture models.

```bash
composer test
```

Current coverage is focused on:

- basic attributed object getter generation
- factory wrapper generation from attributed static methods
- `getFactoryModelObject` wrapper generation
- invalid `#[ObjectFactory]` usage on non-public or non-static methods

The tests generate a temporary manager file under `src/Generated/` during execution and clean it up afterward.

### Migrating Legacy `@om-*` Docblocks

Legacy class docblocks can be converted to attributes with the bundled migration script.

Dry run:

```bash
composer migrate-docblocks -- --dry-run path/to/src
```

Apply changes:

```bash
composer migrate-docblocks -- path/to/src
```

What the migrator does:

- converts class-level `@om-method`, `@om-name`, `@om-namespace`, and `@om-gc` tags into `#[ObjectManager(...)]` using `ObjectManagerMethod`
- converts each `@om-factory-X-method` entry into `#[ObjectFactory]` on the matching class method
- removes legacy `@om-*` lines from the class docblock while preserving unrelated docblock text
- adds the required `use GCWorld\ObjectManager\Attributes\ObjectManager;`, `use GCWorld\ObjectManager\Attributes\ObjectFactory;`, and `use GCWorld\ObjectManager\Enums\ObjectManagerMethod;` imports

The script only migrates factory metadata when every referenced legacy factory method can be resolved in the class. If a declared factory method is missing, the file is skipped and a warning is reported so existing metadata is not destroyed.
