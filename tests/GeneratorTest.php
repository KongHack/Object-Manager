<?php
namespace GCWorld\ObjectManager\Tests;

use GCWorld\ObjectManager\Generator;
use PHPUnit\Framework\TestCase;

class GeneratorTest extends TestCase
{
    private string $configPath;
    private bool $configCreated = false;
    private string $generatedPath;
    private array $tempFixtureDirs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $repoRoot = dirname(__DIR__);
        $configDir = $repoRoot.'/config';
        $this->configPath = $configDir.'/config.yml';
        $this->generatedPath = $repoRoot.'/src/Generated/GeneratedManager.php';

        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        if (!file_exists($this->configPath)) {
            file_put_contents($this->configPath, "{}\n");
            $this->configCreated = true;
        }

        if (file_exists($this->generatedPath)) {
            unlink($this->generatedPath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->generatedPath)) {
            unlink($this->generatedPath);
        }

        if ($this->configCreated && file_exists($this->configPath)) {
            unlink($this->configPath);
        }

        foreach ($this->tempFixtureDirs as $dir) {
            $this->deleteDirectory($dir);
        }

        parent::tearDown();
    }

    public function testGeneratesGetterForSimpleAttributeModel(): void
    {
        require_once __DIR__.'/Fixtures/AttributeModels/SimpleModel.php';
        $fixtureDir = $this->makeFixtureDir([
            __DIR__.'/Fixtures/AttributeModels/SimpleModel.php',
        ]);

        $generator = new Generator();
        $generator->addPath($fixtureDir);
        self::assertTrue($generator->generate());

        $generated = $this->readGeneratedManager();
        self::assertStringContainsString(
            'public function getSimpleModel(mixed $primary_id = null, ?array $defaults = null)',
            $generated
        );
        self::assertStringContainsString(
            '$this->garbageCollect(\'\\GCWorld\\ObjectManager\\Tests\\Fixtures\\AttributeModels\\SimpleModel\', 25);',
            $generated
        );
    }

    public function testGeneratesFactoryWrappersFromAttributedStaticMethods(): void
    {
        require_once __DIR__.'/Fixtures/AttributeModels/FactoryModel.php';
        $fixtureDir = $this->makeFixtureDir([
            __DIR__.'/Fixtures/AttributeModels/FactoryModel.php',
        ]);

        $generator = new Generator();
        $generator->addPath($fixtureDir);
        self::assertTrue($generator->generate());

        $generated = $this->readGeneratedManager();
        self::assertStringContainsString(
            'public function getFactoryModelByByUuid(string $uuid)',
            $generated
        );
        self::assertStringContainsString(
            'return $this->getFactoryObject(\'\\GCWorld\\ObjectManager\\Tests\\Fixtures\\AttributeModels\\FactoryModel\', \'factoryByUuid\', false, $uuid, $uuid);',
            $generated
        );
        self::assertStringContainsString(
            'public function getFactoryModelByLookup(string $email, string $name, mixed $primary_id = null)',
            $generated
        );
        self::assertStringContainsString(
            'return $this->getFactoryObject(\'\\GCWorld\\ObjectManager\\Tests\\Fixtures\\AttributeModels\\FactoryModel\', \'factoryLookup\', false, $primary_id, $email, $name);',
            $generated
        );
    }

    public function testGeneratesFactoryModelObjectWrapper(): void
    {
        require_once __DIR__.'/Fixtures/AttributeModels/FactoryModelObject.php';
        $fixtureDir = $this->makeFixtureDir([
            __DIR__.'/Fixtures/AttributeModels/FactoryModelObject.php',
        ]);

        $generator = new Generator();
        $generator->addPath($fixtureDir);
        self::assertTrue($generator->generate());

        $generated = $this->readGeneratedManager();
        self::assertStringContainsString(
            'return $this->getFactoryModelObject(\'\\GCWorld\\ObjectManager\\Tests\\Fixtures\\AttributeModels\\FactoryModelObject\', \'factoryLookup\', false, $primary_id, $email);',
            $generated
        );
    }

    public function testRejectsNonPublicStaticObjectFactoryMethods(): void
    {
        require_once __DIR__.'/Fixtures/AttributeModels/InvalidFactoryModel.php';
        $fixtureDir = $this->makeFixtureDir([
            __DIR__.'/Fixtures/AttributeModels/InvalidFactoryModel.php',
        ]);

        $generator = new Generator();
        $generator->addPath($fixtureDir);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ObjectFactory attribute must be declared on a public static method');
        $generator->generate();
    }

    private function readGeneratedManager(): string
    {
        self::assertFileExists($this->generatedPath);
        $generated = file_get_contents($this->generatedPath);
        self::assertNotFalse($generated);

        return $generated;
    }

    /**
     * @param string[] $sourceFiles
     */
    private function makeFixtureDir(array $sourceFiles): string
    {
        $dir = sys_get_temp_dir().'/object-manager-tests-'.bin2hex(random_bytes(8));
        mkdir($dir, 0755, true);
        $this->tempFixtureDirs[] = $dir;

        foreach ($sourceFiles as $sourceFile) {
            copy($sourceFile, $dir.'/'.basename($sourceFile));
        }

        return $dir;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
