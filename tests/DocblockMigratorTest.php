<?php
namespace GCWorld\ObjectManager\Tests;

use GCWorld\ObjectManager\DocblockMigrator;
use PHPUnit\Framework\TestCase;

class DocblockMigratorTest extends TestCase
{
    public function testTransformsLegacyDocblocksIntoAttributes(): void
    {
        $source = <<<'PHP'
<?php
namespace Example\Model;

use DateTimeImmutable;

/**
 * User model.
 *
 * @om-method getFactoryObject
 * @om-name Account
 * @om-namespace \Legacy\Model
 * @om-gc 12
 * @om-factory-1-method factoryByUuid
 * @om-factory-1-arg string $uuid
 * @om-factory-2-method factoryLookup
 * @om-factory-2-arg string $email
 */
class User
{
    public static function factoryByUuid(string $uuid): self
    {
        return new self();
    }

    public static function factoryLookup(string $email): self
    {
        return new self();
    }
}
PHP;

        $migrator = new DocblockMigrator();
        $result = $migrator->transformContents($source, 'User.php');

        self::assertTrue($result['changed']);
        self::assertSame([], $result['warnings']);
        self::assertStringContainsString(
            "use GCWorld\\ObjectManager\\Attributes\\ObjectManager;\n",
            $result['contents']
        );
        self::assertStringContainsString(
            "use GCWorld\\ObjectManager\\Enums\\ObjectManagerMethod;\n",
            $result['contents']
        );
        self::assertStringContainsString(
            "use GCWorld\\ObjectManager\\Attributes\\ObjectFactory;\n",
            $result['contents']
        );
        self::assertStringContainsString(
            "#[ObjectManager(method: ObjectManagerMethod::GetFactoryObject, name: 'Account', namespace: '\\\\Legacy\\\\Model', gc: 12)]\nclass User",
            $result['contents']
        );
        self::assertStringContainsString("/**\n * User model.\n */", $result['contents']);
        self::assertStringContainsString("#[ObjectFactory]\n    public static function factoryByUuid", $result['contents']);
        self::assertStringContainsString("#[ObjectFactory]\n    public static function factoryLookup", $result['contents']);
        self::assertStringNotContainsString('@om-method', $result['contents']);
    }

    public function testSkipsClassWhenLegacyFactoryMethodCannotBeResolved(): void
    {
        $source = <<<'PHP'
<?php
namespace Example\Model;

/**
 * @om-method getFactoryObject
 * @om-factory-1-method factoryMissing
 */
class User
{
    public static function factoryLookup(string $email): self
    {
        return new self();
    }
}
PHP;

        $migrator = new DocblockMigrator();
        $result = $migrator->transformContents($source, 'User.php');

        self::assertFalse($result['changed']);
        self::assertCount(1, $result['warnings']);
        self::assertStringContainsString('factoryMissing', $result['warnings'][0]);
        self::assertStringContainsString('@om-method getFactoryObject', $result['contents']);
    }
}
