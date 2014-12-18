<?php
namespace GCWorld\ObjectManager;

class ObjectManager
{
    private static $instance	= null;
    private $objects			= array();
    private $namespaces         = array();

    private function __construct(){}
    private function __clone() {}

    /**
     * @return ObjectManager|null
     */
    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new self();
            self::$instance->addNamespace(__NAMESPACE__);
        }
        return self::$instance;
    }

    /**
     * @param $namespace
     */
    public function addNamespace($namespace)
    {
        array_unshift($this->namespaces, $namespace);
    }

    /**
     * @param $class
     * @param null $id
     * @param null $arr
     * @param bool $forceNew
     * @return mixed
     * @throws \Exception
     */
    public function getObject($class, $id = null, $arr = null, $forceNew = false)
    {
        //Class should not contain a namespace.  If it does, we are dealing with an absolute and should not prepend.
        if(strstr($class,'\\'))
        {
            if(!class_exists($class))
            {
                throw new \Exception('Class Does Not Exist');
            }
        }
        else
        {
            //Cycle up through namespaces to find this class.
            foreach($this->namespaces as $namespace)
            {
                $concat = '\\'.trim($namespace,'\\').'\\'.$class;
                if(class_exists($concat))
                {
                    $class = $concat;
                    break;
                }
            }
        }

        //Let's see if we have a reflection cached.
        $path = $this->cacheLocation($class);
        if(!file_exists($path))
        {
            $set = 'unknown';
            $test = new \ReflectionClass($class);
            if($test->implementsInterface('\GCWorld\ORM\GeneratedInterface'))
            {
                $set = 'GeneratedInterface';
            }
            elseif(defined($class.'::CLASS_PRIMARY')) //second test, check to see if this has the CLASS_PRIMARY constant.  If so, we're good.
            {
                $set = 'CLASS_PRIMARY';
            }

            file_put_contents($set, $path);
        }
        else
        {
            $set = file_get_contents($path);
        }

        if(!class_exists($class))
        {
            throw new \Exception('Class Does Not Exist (2)');
        }

        if($set == 'GeneratedInterface' || $set == 'CLASS_PRIMARY')
        {
            if($id == null && is_array($arr))
            {
                $primary_key = constant($class.'::CLASS_PRIMARY');
                $id = $arr[$primary_key];
            }

            if(!isset($this->objects[$class][$id]) || $forceNew)
            {
                if(is_array($arr))
                {
                    $this->objects[$class][$id] = new $class(null, $arr);
                }
                else
                {
                    $this->objects[$class][$id] = new $class($id);
                }
            }
            return $this->objects[$class][$id];
        }
        else
        {
            // This isn't something we can track, so just return a new one of it.
            // Always pass both args to be safe.
            return new $class($id,$arr);
        }
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
    private function cacheLocation($fullClass)
    {
        $generated = dirname(__FILE__).'../../../generated/';
        if(!is_dir($generated))
        {
            mkdir($generated);
        }
        $temp = explode('\\',$fullClass);
        $filename = array_pop($temp);
        $generated .= implode('/',$temp).'/';
        if(!is_dir($generated))
        {
            mkdir($generated,0755,true);
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
        while(count($this->objects[$class] > $count))
        {
            array_shift($this->objects[$class]);
        }
    }

}
