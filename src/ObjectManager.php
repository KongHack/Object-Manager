<?php
namespace GCWorld\ObjectManager;

class ObjectManager
{
    protected static $instance        = null;
    protected        $objects         = array();
    protected        $namespaces      = array();
    protected        $master_location = null;

    protected function __construct()
    {
        $this->master_location = __DIR__;
    }

    protected function __clone()
    {
    }

    /**
     * @return ObjectManager|null
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
            self::$instance->addNamespace(__NAMESPACE__);
        }

        return self::$instance;
    }

    /**
     * @param $namespace
     * @return $this
     */
    public function addNamespace($namespace)
    {
        array_unshift($this->namespaces, $namespace);

        return self::$instance;
    }

    /**
     * @param      $class
     * @param null $id
     * @param null $arr
     * @param bool $forceNew
     * @return mixed
     * @throws \Exception
     */
    public function getObject($class, $id = null, $arr = null, $forceNew = false)
    {
        $type = $this->getClassType($class);

        if ($type == 'GeneratedInterface' || $type == 'CLASS_PRIMARY') {
            if ($id == null && is_array($arr)) {
                $primary_key = constant($class.'::CLASS_PRIMARY');
                $id          = $arr[$primary_key];
            }

            if (!isset($this->objects[$class][$id]) || $forceNew) {
                if (is_array($arr)) {
                    $this->objects[$class][$id] = new $class(null, $arr);
                } else {
                    $this->objects[$class][$id] = new $class($id);
                }
            }

            return $this->objects[$class][$id];
        } else {
            // This isn't something we can track, so just return a new one of it.
            // Always pass both args to be safe.
            return new $class($id, $arr);
        }
    }

    /**
     * @param            $class
     * @param bool|false $forceNew
     * @param            ...$keys
     * @return mixed
     * @throws \Exception
     */
    public function getMultiObject($class, $forceNew = false, ...$keys)
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

    private function getClassType($class)
    {
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

        //Let's see if we have a reflection cached.
        $path = $this->cacheLocation($class);
        if (!file_exists($path)) {
            $set  = 'unknown';
            $test = new \ReflectionClass($class);
            if ($test->implementsInterface('\GCWorld\ORM\GeneratedInterface') || $test->implementsInterface('\GCWorld\ORM\Interfaces\GeneratedInterface')) {
                $set = 'GeneratedInterface';
            } elseif ($test->implementsInterface('\GCWorld\ORM\GeneratedMultiInterface') || $test->implementsInterface('\GCWorld\ORM\Interfaces\GeneratedMultiInterface')) {
                $set = 'GeneratedMultiInterface';
            } elseif (defined($class.'::CLASS_PRIMARY')) { //second test, check to see if this has the CLASS_PRIMARY constant.  If so, we're good.
                $set = 'CLASS_PRIMARY';
            }

            file_put_contents($path, $set);
        } else {
            $set = file_get_contents($path);
        }

        if (!class_exists($class)) {
            throw new \Exception('Class Does Not Exist (2)');
        }

        return $set;
    }

    /**
     * @param $class
     * @param $id
     * @return void
     */
    public function clearObject($class, $id)
    {
        unset($this->objects[$class][$id]);
    }

    /**
     * @return string
     */
    protected function cacheLocation($fullClass)
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
     * @param $class
     * @param $count
     * @return void
     */
    public function garbageCollect($class, $count)
    {
        if (array_key_exists($class, $this->objects)) {
            while (count($this->objects[$class]) > $count) {
                array_shift($this->objects[$class]);
            }
        }

    }
}
