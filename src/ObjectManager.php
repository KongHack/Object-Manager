<?php
namespace GCWorld\ObjectManager;

/**
 * Class ObjectManager
 * @package GCWorld\ObjectManager
 */
class ObjectManager
{
    const APCU_KEY = 'GCObjMan';

    protected static $instance = null;

    protected $objects         = [];
    protected $namespaces      = [];
    protected $object_types    = [];
    protected $config_location = null;
    protected $master_location = null;
    protected $objects_changed = false;

    /**
     * ObjectManager constructor.
     */
    protected function __construct()
    {
        $this->master_location = __DIR__;
        $this->config_location = $this->master_location.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.
            'config'.DIRECTORY_SEPARATOR;
        if (function_exists('apcu_fetch')) {
            $this->object_types = apcu_fetch(self::APCU_KEY);
            if (!is_array($this->object_types) || count($this->object_types) < 1) {
                $this->object_types    = [];
                $this->objects_changed = true;
            }
        } else {
            if (!is_dir($this->config_location)) {
                mkdir($this->config_location);
            }
            if (file_exists($this->config_location.'config.php')) {
                $this->object_types = include($this->config_location.'config.php');
                if (!is_array($this->object_types)) {
                    $this->object_types = [];
                }
            } else {
                $this->objects_changed = true;
            }
        }
    }

    /**
     * ObjectManager destructor.
     */
    public function __destruct()
    {
        if ($this->objects_changed) {
            if (function_exists('apcu_store')) {
                apcu_store(self::APCU_KEY, $this->object_types);
            } else {
                $contents = "<?php\n return ".var_export($this->object_types, true).";\n";
                file_put_contents($this->config_location.'config.php', $contents);
            }
        }
    }

    /**
     * ObjectManager clone.
     */
    protected function __clone()
    {
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            /** @var ObjectManager instance */
            self::$instance = new static();
            self::$instance->addNamespace(__NAMESPACE__);
        }

        return self::$instance;
    }

    /**
     * @param string $namespace
     * @return $this
     */
    public function addNamespace(string $namespace)
    {
        if (!in_array($namespace, $this->namespaces)) {
            array_unshift($this->namespaces, $namespace);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function debugGetEverything()
    {
        return [
            'objects'      => $this->objects,
            'namespaces'   => $this->namespaces,
            'object_types' => $this->object_types,
        ];
    }

    /**
     * @param string     $class
     * @param mixed      $primaryId
     * @param array|null $rawArray
     * @param bool       $forceNew
     * @return mixed
     * @throws \Exception
     */
    public function getObject(string $class, $primaryId = null, array $rawArray = null, bool $forceNew = false)
    {
        // Handle 0's equating to null
        $primaryId = ($primaryId === 0 ? null : $primaryId);
        // Same for empty strings
        $primaryId = ($primaryId === '' ? null : $primaryId);

        $type = $this->getClassType($class);

        if ($type == 'GeneratedInterface' || $type == 'CLASS_PRIMARY') {
            if ($primaryId === null && is_array($rawArray)) {
                $primary_key = constant($class.'::CLASS_PRIMARY');
                $primaryId   = $rawArray[$primary_key];
            }

            $key = empty($primaryId) ? 'OM_NEW' : $primaryId;

            if (!isset($this->objects[$class][$key]) || $forceNew) {
                if (is_array($rawArray)) {
                    $this->objects[$class][$key] = new $class(null, $rawArray);
                } else {
                    $this->objects[$class][$key] = new $class($primaryId);
                }
            }

            return $this->objects[$class][$key];
        } else {
            // This isn't something we can track, so just return a new one of it.
            // Always pass both args to be safe.
            return new $class($primaryId, $rawArray);
        }
    }

    /**
     * @param string $class
     * @param string $staticMethod
     * @param bool   $forceNew
     * @param mixed  $primaryId
     * @param mixed  ...$args
     * @return mixed
     * @throws \Exception
     */
    public function getFactoryObject(
        string $class,
        string $staticMethod,
        bool $forceNew = false,
        $primaryId = null,
        ...$args
    ){
        // Handle 0's equating to null
        $primaryId = ($primaryId === 0 ? null : $primaryId);
        // Same for empty strings
        $primaryId = ($primaryId === '' ? null : $primaryId);


        $type = $this->getClassType($class);

        if ($type === 'GeneratedInterface' || $type === 'CLASS_PRIMARY') {
            if (!method_exists($class, $staticMethod)) {
                throw new \Exception('Method "'.$staticMethod.'" does not exist in "'.$class.'"');
            }
            if (!isset($this->objects[$class][$primaryId]) || $forceNew) {
                if (count($args) > 0) {
                    $this->objects[$class][$primaryId] = $class::$staticMethod(...$args);
                } else {
                    $this->objects[$class][$primaryId] = $class::$staticMethod();
                }
            }

            return $this->objects[$class][$primaryId];
        } else {
            // This isn't something we can track, so just return a new one of it.
            // Always pass both args to be safe.
            if (count($args) > 0) {
                return $class::$staticMethod(...$args);
            } else {
                return $class::$staticMethod();
            }
        }
    }

    /**
     * @param string $class
     * @param bool   $forceNew
     * @param mixed  ...$keys
     * @return mixed
     */
    public function getMultiObject(string $class, bool $forceNew = false, ...$keys)
    {
        $type = $this->getClassType($class);
        if ($type == 'GeneratedMultiInterface') {
            $xKey = implode('-', $keys);
            if (!isset($this->objects[$class][$xKey]) || $forceNew) {
                $this->objects[$class][$xKey] = new $class(...$keys);
            }

            return $this->objects[$class][$xKey];
        } else {
            return new $class(...$keys);
        }
    }

    /**
     * @param string $class
     * @return string
     * @throws \Exception
     */
    private function getClassType(string $class): string
    {
        if (array_key_exists($class, $this->object_types)) {
            return $this->object_types[$class];
        }

        //If the first character is a backslash, assume this is a fully defined namespace
        if (substr($class, 0, 1) == '\\') {
            if (!class_exists($class)) {
                throw new \Exception('Class Does Not Exist');
            }
        } else {
            //Cycle up through namespaces to find this class.
            foreach ($this->namespaces as $namespace) {
                $concat = '\\'.trim($namespace, '\\').'\\'.$class;
                if (class_exists($concat)) {
                    $class = $concat;
                    break;
                }
            }
        }

        $set        = 'unknown';
        $implements = class_implements($class);

        if (in_array('\GCWorld\ORM\GeneratedInterface', $implements)
            || in_array('\GCWorld\ORM\Interfaces\GeneratedInterface', $implements)
        ) {
            $set = 'GeneratedInterface';
        } elseif (in_array('\GCWorld\ORM\GeneratedMultiInterface', $implements)
            || in_array('\GCWorld\ORM\Interfaces\GeneratedMultiInterface', $implements)
        ) {
            $set = 'GeneratedMultiInterface';
        } elseif (defined($class.'::CLASS_PRIMARY')) {
            //second test, check to see if this has the CLASS_PRIMARY constant.  If so, we're good.
            $set = 'CLASS_PRIMARY';
        }

        if (!class_exists($class)) {
            throw new \Exception('Class Does Not Exist (2)');
        }

        $this->object_types[$class] = $set;
        $this->objects_changed      = true;

        return $set;
    }

    /**
     * @param string $class
     * @param mixed  $primaryId
     * @return void
     */
    public function clearObject(string $class, $primaryId): void
    {
        unset($this->objects[$class][$primaryId]);
    }

    /**
     * @param string $fullClass
     * @return string
     */
    protected function cacheLocation(string $fullClass): string
    {
        $generated = $this->master_location.DIRECTORY_SEPARATOR.'Generated/';
        if (!is_dir($generated)) {
            if (!mkdir($generated, 0755, true)) {
                if (function_exists('d')) {
                    d($generated);
                }
            }
        }
        $temp     = explode('\\', $fullClass);
        $filename = array_pop($temp);
        $generated .= implode('/', $temp).'/';
        if (!is_dir($generated)) {
            mkdir($generated, 0755, true);
        }
        $generated .= $filename.'.GCObjectManager';

        return $generated;
    }

    /**
     * Removes oldest objects from memory
     * @param string $class
     * @param int    $count
     * @return void
     */
    public function garbageCollect(string $class, int $count): void
    {
        if (array_key_exists($class, $this->objects)) {
            while (count($this->objects[$class]) > $count) {
                array_shift($this->objects[$class]);
            }
        }
    }

    /**
     * @return void
     */
    public function garbageCollectAll()
    {
        $this->objects = [];
    }

    /**
     * Will clear the APCU cache as well as the config.php file
     *
     * @return void
     */
    public function purgeConfig(): void
    {
        if (function_exists('apcu_delete')) {
            apcu_delete(self::APCU_KEY);
        }
        if (file_exists($this->config_location.'config.php')) {
            unlink($this->config_location.'config.php');
        }
    }


    /**
     * @param string     $modelName
     * @param mixed|null $primary_id
     * @param array|null $defaults
     * @param bool       $forceNew
     * @return mixed
     * @throws \Exception
     */
    public function getModel(string $modelName, $primary_id = null, array $defaults = null, bool $forceNew = false)
    {
        foreach ($this->namespaces as $namespace) {
            if (substr($namespace, -1) !== '\\') {
                $namespace .= '\\';
            }

            $className = $namespace.$modelName;
            if (class_exists($className)) {
                return $this->getObject($className, $primary_id, $defaults, $forceNew);
            }
        }
        throw new \Exception('Model Not Found: '.$modelName.' within namespace(s) '.print_r($this->namespaces, true));
    }

    /**
     * @param string $modelName
     * @param string $staticMethod
     * @param bool   $forceNew
     * @param int    $primaryId
     * @param mixed  ...$args
     * @return mixed
     * @throws \Exception
     */
    public function getFactoryModelObject(
        string $modelName,
        string $staticMethod,
        bool $forceNew = false,
        ...$args
    ){
        foreach ($this->namespaces as $namespace) {
            if (substr($namespace, -1) !== '\\') {
                $namespace .= '\\';
            }

            $className = $namespace.$modelName;
            if (class_exists($className)) {
                return $this->getFactoryObject($className, $staticMethod, $forceNew, ...$args);
            }
        }
        throw new \Exception('Factory Model Not Found: '.$modelName.' within namespace(s) '.print_r($this->namespaces,
                true));
    }

}
