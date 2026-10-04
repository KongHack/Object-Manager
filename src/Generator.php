<?php
namespace GCWorld\ObjectManager;

use Exception;
use GCWorld\ObjectManager\Attributes\ObjectFactoryAttribute;
use GCWorld\ObjectManager\Attributes\ObjectManagerAttribute;
use GCWorld\ObjectManager\Enums\ObjectManagerMethod;
use GCWorld\Utilities\Traits\General;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Class Generator
 * @package GCWorld\ObjectManager
 */
class Generator
{
    use General;

    const CLASS_NAME = 'GeneratedManager';

    protected $master_location = null;
    protected $config          = [];
    private $open_files        = [];
    private $open_files_level  = [];
    private $paths             = [];
    private $debug             = false;

    /**
     * Generator constructor.
     */
    public function __construct()
    {
        $this->master_location  = __DIR__;

        $cConfig      = new Config();
        $config       = $cConfig->getConfig();
        $this->config = $config;

        foreach($this->config as $model => $definition) {
            if(str_starts_with($model, 'ExampleModelName')) {
                unset($this->config[$model]);
                continue;
            }
        }
    }

    /**
     * @param bool $debug
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function addPath(string $path): static
    {
        $this->paths[] = $path;

        return $this;
    }


    /**
     * @return bool
     */
    public function generate(): bool
    {
        if($this->debug) {
            echo PHP_EOL;
            echo 'Config',PHP_EOL;
            print_r($this->config);
            echo 'Paths',PHP_EOL;
            print_r($this->paths);
            echo PHP_EOL;
        }

        $config = $this->normalizeConfig($this->config);
        if(count($this->paths) > 0) {
            $extra = $this->generateAnnotatedConfig();
            if($this->debug) {
                echo PHP_EOL;
                echo 'EXTRA',PHP_EOL;
                print_r($extra);
                echo PHP_EOL;
            }

            // We are using this order so that the flat file will override
            $config = array_replace($extra, $config);
        }
        $this->config = $config;

        $this->validateGeneratedMethodNames();

        if($this->debug) {
            echo PHP_EOL;
            echo 'FINAL CONFIG',PHP_EOL;
            print_r($this->config);
            echo PHP_EOL;
        }

        $path = $this->master_location.DIRECTORY_SEPARATOR.'Generated/';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filename = self::CLASS_NAME.'.php';
        $fh       = $this->fileOpen($path.$filename);

        $this->fileWrite($fh, '<?php'.PHP_EOL);
        $this->fileWrite($fh, 'namespace GCWorld\\ObjectManager\\Generated;'.PHP_EOL.PHP_EOL);
        $this->fileWrite($fh, 'use \\GCWorld\\ObjectManager\\ObjectManager;'.PHP_EOL.PHP_EOL);

        $this->fileWrite($fh, '/**'.PHP_EOL);
        $this->fileWrite($fh, ' * Class '.self::CLASS_NAME.PHP_EOL);
        $this->fileWrite($fh, ' */'.PHP_EOL);
        $this->fileWrite($fh,'class '.self::CLASS_NAME.' extends ObjectManager'.PHP_EOL.'{'.PHP_EOL);
        $this->fileBump($fh);


        foreach($this->config as $definition) {
            if (!array_key_exists('method', $definition)) {
                continue;
            }

            $model = $definition['model'];
            $cName = $definition['class'];
            $fName = empty($definition['name']) ? $model : trim($definition['name']);

            switch ($definition['method']) {
                case 'getModel':
                    $this->fileWrite($fh, PHP_EOL);
                    $this->fileWrite($fh, '/**'.PHP_EOL);
                    $this->fileWrite($fh, ' * @param mixed|null $primary_id'.PHP_EOL);
                    $this->fileWrite($fh, ' * @param array|null $defaults'.PHP_EOL);
                    $this->fileWrite($fh, ' *'.PHP_EOL);
                    $this->fileWrite($fh, ' * @return '.$cName.PHP_EOL);
                    $this->fileWrite($fh, ' */'.PHP_EOL);
                    $this->fileWrite($fh,
                        'public function get'.$fName.'(mixed $primary_id = null, ?array $defaults = null)'.PHP_EOL);
                    $this->fileWrite($fh, '{'.PHP_EOL);
                    $this->fileBump($fh);
                    if (array_key_exists('gc', $definition) && $definition['gc'] > 0) {
                        $this->fileWrite($fh,
                            '$this->garbageCollect(\''.$cName.'\', '.$definition['gc'].');'.PHP_EOL.PHP_EOL);
                    }
                    $this->fileWrite(
                        $fh,
                        'return $this->getModel(\''.$definition['model_lookup'].'\', $primary_id, $defaults);'.PHP_EOL
                    );
                    $this->fileDrop($fh);
                    $this->fileWrite($fh, '}'.PHP_EOL);
                    $this->fileWrite($fh, PHP_EOL);
                    break;

                case 'getObject':
                    $this->fileWrite($fh, PHP_EOL);
                    $this->fileWrite($fh, '/**'.PHP_EOL);
                    $this->fileWrite($fh, ' * @param mixed|null $primary_id'.PHP_EOL);
                    $this->fileWrite($fh, ' * @param array|null $defaults'.PHP_EOL);
                    $this->fileWrite($fh, ' *'.PHP_EOL);
                    $this->fileWrite($fh, ' * @return '.$cName.PHP_EOL);
                    $this->fileWrite($fh, ' */'.PHP_EOL);
                    $this->fileWrite($fh,
                        'public function get'.$fName.'(mixed $primary_id = null, ?array $defaults = null)'.PHP_EOL);
                    $this->fileWrite($fh, '{'.PHP_EOL);
                    $this->fileBump($fh);
                    if (array_key_exists('gc', $definition) && $definition['gc'] > 0) {
                        $this->fileWrite($fh,
                            '$this->garbageCollect(\''.$cName.'\', '.$definition['gc'].');'.PHP_EOL.PHP_EOL);
                    }
                    $this->fileWrite($fh, 'return $this->getObject(\''.$cName.'\', $primary_id, $defaults);'.PHP_EOL);
                    $this->fileDrop($fh);
                    $this->fileWrite($fh, '}'.PHP_EOL);
                    $this->fileWrite($fh, PHP_EOL);
                    break;

                case 'getFactoryObject':
                case 'getFactoryModelObject':
                    // Check to see if we have a primary in the args.
                    $primary_name = null;
                    if(defined($cName.'::CLASS_PRIMARY')) {
                        $primary_name = constant($cName.'::CLASS_PRIMARY');
                    }

                    foreach ($definition['factory'] as $method => $methodArgs) {
                        $primary_arg      = false;
                        $primary_variable = '$primary_id';
                        $this->fileWrite($fh, PHP_EOL);
                        $this->fileWrite($fh, '/**'.PHP_EOL);
                        $maxLeft   = 8;
                        $variables = [];
                        foreach ($methodArgs as $methodArg) {
                            $tmp         = explode(' ', $methodArg);
                            $maxLeft     = max($maxLeft, strlen($tmp[0]));
                            $variables[] = $tmp[1];
                            if($primary_name != null && $tmp[1] == '$'.$primary_name) {
                                $primary_arg      = true;
                                $primary_variable = $tmp[1];
                            }
                        }
                        foreach ($methodArgs as $methodArg) {
                            $tmp    = explode(' ', $methodArg);
                            $tmp[0] = str_pad($tmp[0], $maxLeft, ' ', STR_PAD_RIGHT);
                            $this->fileWrite($fh, ' * @param '.implode(' ', $tmp).PHP_EOL);
                        }
                        if(!$primary_arg) {
                            $this->fileWrite($fh,
                                ' * @param '.str_pad('mixed|null', $maxLeft, ' ', STR_PAD_RIGHT).' $primary_id'.PHP_EOL);
                        }
                        $this->fileWrite($fh, ' *'.PHP_EOL);
                        $this->fileWrite($fh, ' * @return '.$cName.PHP_EOL);
                        $this->fileWrite($fh, ' */'.PHP_EOL);

                        $translatedMethod = $fName.str_replace('factory','By',$method);

                        $signatureArgs = $methodArgs;
                        if(!$primary_arg) {
                            $signatureArgs[] = 'mixed $primary_id = null';
                        }

                        $this->fileWrite($fh,
                            'public function get'.$translatedMethod.'('.implode(', ', $signatureArgs).')'.PHP_EOL);
                        $this->fileWrite($fh, '{'.PHP_EOL);
                        $this->fileBump($fh);
                        if (array_key_exists('gc', $definition) && $definition['gc'] > 0) {
                            $this->fileWrite($fh,
                                '$this->garbageCollect(\''.$cName.'\', '.$definition['gc'].');'.PHP_EOL.PHP_EOL);
                        }
                        $callTarget = $definition['method'];
                        $callArgs   = [
                            '\''.$cName.'\'',
                            '\''.$method.'\'',
                            'false',
                            $primary_variable,
                        ];
                        $callArgs   = array_merge($callArgs, $variables);
                        $this->fileWrite($fh,
                            'return $this->'.$callTarget.'('.implode(', ', $callArgs).');'.PHP_EOL);
                        $this->fileDrop($fh);
                        $this->fileWrite($fh, '}'.PHP_EOL);
                        $this->fileWrite($fh, PHP_EOL);
                    }
                    break;
            }
        }

        $this->fileDrop($fh);
        $this->fileWrite($fh, '}'.PHP_EOL.PHP_EOL);
        $this->fileClose($fh);

        return true;
    }

    /**
     * @param string $filename
     * @return string
     */
    protected function fileOpen(string $filename): string
    {
        $key                          = str_replace('.', '', microtime(true));
        $this->open_files[$key]       = fopen($filename, 'w');
        $this->open_files_level[$key] = 0;

        return $key;
    }

    /**
     * @param mixed  $key
     * @param string $string
     * @return void
     */
    protected function fileWrite(mixed $key, string $string): void
    {
        fwrite($this->open_files[$key], str_repeat(' ', $this->open_files_level[$key] * 4).$string);
    }

    /**
     * @param mixed $key
     * @return void
     */
    protected function fileBump(mixed $key): void
    {
        ++$this->open_files_level[$key];
    }

    /**
     * @param mixed $key
     * @return void
     */
    protected function fileDrop(mixed $key): void
    {
        --$this->open_files_level[$key];
    }

    /**
     * @param mixed $key
     * @return void
     */
    protected function fileClose(mixed $key): void
    {
        fclose($this->open_files[$key]);
        unset($this->open_files[$key]);
        unset($this->open_files_level[$key]);
    }


    /**
     * @return array
     */
    private function generateAnnotatedConfig(): array
    {
        $return = [];
        if (count($this->paths) > 0) {
            foreach ($this->paths as $path) {
                $classFiles = self::glob_recursive(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.php');
                foreach ($classFiles as $file) {
                    foreach ($this->discoverClassesInFile($file) as $classString) {
                        if($this->debug) {
                            echo ' - Analyzing Class File: ',$file,PHP_EOL;
                            echo ' - - Found FQCN: ',$classString,PHP_EOL;
                        }
                        if (class_exists($classString)) {
                            $thisClass = new ReflectionClass($classString);
                            $config    = $this->extractAttributeConfig($thisClass);
                            if ($config) {
                                $return[$this->classIdentity($config['class'])] = $config;
                            } elseif($this->debug) {
                                echo ' - [!!] No Config Found', PHP_EOL;
                            }
                        } elseif($this->debug) {
                            echo ' - [!!] CLASS NOT FOUND',PHP_EOL;
                        }
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Convert legacy short-name-keyed configuration to the internal FQCN-keyed format.
     *
     * @param array $config
     * @return array
     */
    private function normalizeConfig(array $config): array
    {
        $normalized = [];
        foreach ($config as $model => $definition) {
            $namespace = $definition['namespace'] ?? '';
            if (!str_ends_with($namespace, '\\')) {
                $namespace .= '\\';
            }

            $model                   = $definition['model'] ?? $model;
            $class                   = $definition['class'] ?? $namespace.$model;
            $definition['model']     = $model;
            $definition['class']     = '\\'.ltrim($class, '\\');
            $definition['namespace'] = $namespace;
            $definition['model_lookup'] ??= $model;

            $normalized[$this->classIdentity($definition['class'])] = $definition;
        }

        return $normalized;
    }

    /**
     * @param string $class
     * @return string
     */
    private function classIdentity(string $class): string
    {
        return strtolower(ltrim($class, '\\'));
    }

    /**
     * @return void
     * @throws Exception
     */
    private function validateGeneratedMethodNames(): void
    {
        $methods = [];
        foreach ($this->config as $definition) {
            if (!array_key_exists('method', $definition)) {
                continue;
            }

            $model  = $definition['model'];
            $prefix = empty($definition['name']) ? $model : trim($definition['name']);
            $names  = [];

            if (in_array($definition['method'], ['getModel', 'getObject'], true)) {
                $names[] = 'get'.$prefix;
            } elseif (in_array($definition['method'], ['getFactoryObject', 'getFactoryModelObject'], true)) {
                foreach ($definition['factory'] as $factoryMethod => $methodArgs) {
                    $names[] = 'get'.$prefix.str_replace('factory', 'By', $factoryMethod);
                }
            }

            foreach ($names as $name) {
                $identity = strtolower($name);
                if (array_key_exists($identity, $methods)) {
                    throw new Exception(
                        'Generated method collision for '.$name.': '.$methods[$identity].' and '.
                        $definition['class'].'. Set a unique ObjectManagerAttribute name to disambiguate the getters.'
                    );
                }
                $methods[$identity] = $definition['class'];
            }
        }
    }

    /**
     * @param ReflectionClass $class
     * @return array|bool
     */
    private function extractAttributeConfig(ReflectionClass $class): array|bool
    {
        $attributes = $class->getAttributes(ObjectManagerAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
        if (count($attributes) < 1) {
            return false;
        }

        /** @var ObjectManagerAttribute $objectManager */
        $objectManager = $attributes[0]->newInstance();
        $namespace     = $objectManager->namespace;
        if ($namespace === null) {
            $namespace = '\\'.trim($class->getNamespaceName(), '\\');
        }
        $namespace = rtrim($namespace, '\\');
        $className = '\\'.trim($namespace.'\\'.$class->getShortName(), '\\');

        $config = [
            'method'       => $objectManager->method->value,
            'name'         => $objectManager->name ?? $class->getShortName(),
            'namespace'    => $namespace,
            'model'        => $class->getShortName(),
            'model_lookup' => $className,
            'class'        => $className,
            'gc'           => $objectManager->gc,
        ];

        $factory = [];
        if(in_array(
            $objectManager->method,
            [ObjectManagerMethod::GetFactoryObject, ObjectManagerMethod::GetFactoryModelObject],
            true
        )) {
            $factory = $this->extractFactoryMethods($class);
        }
        $config['factory'] = $factory;

        return $config;
    }

    /**
     * @param ReflectionClass $class
     * @return array
     * @throws Exception
     */
    private function extractFactoryMethods(ReflectionClass $class): array
    {
        $factory = [];
        foreach ($class->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            $attributes = $method->getAttributes(ObjectFactoryAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
            if (count($attributes) < 1) {
                continue;
            }

            if (!$method->isPublic() || !$method->isStatic()) {
                throw new Exception(
                    'ObjectFactoryAttribute must be declared on a public static method: '.
                    $class->getName().'::'.$method->getName()
                );
            }

            $factory[$method->getName()] = $this->reflectFactoryArgs($method);
        }

        return $factory;
    }

    /**
     * @param ReflectionMethod $method
     * @return array
     */
    private function reflectFactoryArgs(ReflectionMethod $method): array
    {
        $args = [];
        foreach ($method->getParameters() as $parameter) {
            $args[] = $this->formatParameterDefinition($parameter);
        }

        return $args;
    }

    /**
     * @param ReflectionParameter $parameter
     * @return string
     */
    private function formatParameterDefinition(ReflectionParameter $parameter): string
    {
        $type = $this->formatType($parameter->getType());
        $name = '$'.$parameter->getName();

        if ($parameter->isPassedByReference()) {
            $name = '&'.$name;
        }
        if ($parameter->isVariadic()) {
            $name = '...'.$name;
        }

        return $type.' '.$name;
    }

    /**
     * @param ReflectionType|null $type
     * @return string
     */
    private function formatType(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->formatNamedType($type);
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = [];
            foreach ($type->getTypes() as $namedType) {
                $parts[] = $this->formatNamedType($namedType);
            }

            return implode('|', $parts);
        }

        if ($type instanceof ReflectionIntersectionType) {
            $parts = [];
            foreach ($type->getTypes() as $namedType) {
                $parts[] = $this->formatNamedType($namedType);
            }

            return implode('&', $parts);
        }

        return 'mixed';
    }

    /**
     * @param ReflectionNamedType $type
     * @return string
     */
    private function formatNamedType(ReflectionNamedType $type): string
    {
        $name = $type->getName();
        if (!$type->isBuiltin() && $name !== 'self' && $name !== 'parent' && $name !== 'static') {
            $name = '\\'.ltrim($name, '\\');
        }

        if ($type->allowsNull() && $name !== 'mixed' && !str_contains($name, 'null')) {
            return '?'.$name;
        }

        return $name;
    }

    /**
     * @param string $file
     * @return array
     */
    private function discoverClassesInFile(string $file): array
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            return [];
        }

        $tokens               = token_get_all($contents);
        $classes              = [];
        $namespace            = '';
        $previousSignificant  = null;
        $tokenCount           = count($tokens);

        for ($i = 0; $i < $tokenCount; ++$i) {
            $token = $tokens[$i];
            if (is_string($token)) {
                if (trim($token) !== '') {
                    $previousSignificant = $token;
                }
                continue;
            }

            [$tokenId] = $token;
            if ($tokenId === T_NAMESPACE) {
                $namespace = $this->readNamespaceTokens($tokens, $i + 1);
                $previousSignificant = T_NAMESPACE;
                continue;
            }

            if ($tokenId === T_CLASS) {
                if ($previousSignificant === T_NEW) {
                    continue;
                }

                $className = $this->readNextIdentifier($tokens, $i + 1);
                if ($className !== null) {
                    $classes[] = '\\'.trim($namespace.'\\'.$className, '\\');
                }
                $previousSignificant = T_CLASS;
                continue;
            }

            if (!in_array($tokenId, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $previousSignificant = $tokenId;
            }
        }

        return $classes;
    }

    /**
     * @param array $tokens
     * @param int $start
     * @return string
     */
    private function readNamespaceTokens(array $tokens, int $start): string
    {
        $namespace  = '';
        $tokenCount = count($tokens);
        for ($i = $start; $i < $tokenCount; ++$i) {
            $token = $tokens[$i];
            if (is_string($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                $namespace .= $token[1];
            }
        }

        return trim($namespace, '\\');
    }

    /**
     * @param array $tokens
     * @param int $start
     * @return string|null
     */
    private function readNextIdentifier(array $tokens, int $start): ?string
    {
        $tokenCount = count($tokens);
        for ($i = $start; $i < $tokenCount; ++$i) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
        }

        return null;
    }
}
