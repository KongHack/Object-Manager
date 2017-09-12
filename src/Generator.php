<?php
namespace GCWorld\ObjectManager;

use GCWorld\Utilities\General;
use phpDocumentor\Reflection\DocBlock;

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
            if(strpos($model,'ExampleModelName')===0) {
                unset($this->config[$model]);
                continue;
            }
        }
    }

    /**
     * @param bool $debug
     */
    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function addPath(string $path)
    {
        $this->paths[] = $path;

        return $this;
    }


    /**
     * @return bool
     */
    public function generate()
    {
        if($this->debug) {
            echo PHP_EOL;
            echo 'Config',PHP_EOL;
            print_r($this->config);
            echo 'Paths',PHP_EOL;
            print_r($this->paths);
            echo PHP_EOL;
        }

        if(count($this->paths) > 0) {
            $extra = $this->generateAnnotatedConfig();
            if($this->debug) {
                echo PHP_EOL;
                echo 'EXTRA',PHP_EOL;
                print_r($extra);
                echo PHP_EOL;
            }

            // We are using this order so that the flat file will override
            $this->config = array_merge($extra, $this->config);
        }

        // Make sure we have trailing slashes!
        foreach($this->config as $model => $definition) {
            if(array_key_exists('namespace',$definition) && strpos($definition['namespace'],-1) != '\\') {
                $this->config[$model]['namespace'] .= '\\';
            }
        }

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


        foreach($this->config as $model => $definition) {
            if (!array_key_exists('method', $definition)) {
                continue;
            }

            $cName = $definition['namespace'].$model;
            $fName = empty($definition['name']) ? $model : trim($definition['name']);

            switch ($definition['method']) {
                case 'getModel':
                    $this->fileWrite($fh, PHP_EOL);
                    $this->fileWrite($fh, '/**'.PHP_EOL);
                    $this->fileWrite($fh, ' * @param int|null   $primary_id'.PHP_EOL);
                    $this->fileWrite($fh, ' * @param array|null $defaults'.PHP_EOL);
                    $this->fileWrite($fh, ' *'.PHP_EOL);
                    $this->fileWrite($fh, ' * @return '.$cName.PHP_EOL);
                    $this->fileWrite($fh, ' */'.PHP_EOL);
                    $this->fileWrite($fh,
                        'public function get'.$fName.'(int $primary_id = null, array $defaults = null)'.PHP_EOL);
                    $this->fileWrite($fh, '{'.PHP_EOL);
                    $this->fileBump($fh);
                    if (array_key_exists('gc', $definition) && $definition['gc'] > 0) {
                        $this->fileWrite($fh,
                            '$this->garbageCollect(\''.$cName.'\', '.$definition['gc'].');'.PHP_EOL.PHP_EOL);
                    }
                    $this->fileWrite($fh, 'return $this->getModel(\''.$model.'\', $primary_id, $defaults);'.PHP_EOL);
                    $this->fileDrop($fh);
                    $this->fileWrite($fh, '}'.PHP_EOL);
                    $this->fileWrite($fh, PHP_EOL);
                    break;

                case 'getObject':
                    $this->fileWrite($fh, PHP_EOL);
                    $this->fileWrite($fh, '/**'.PHP_EOL);
                    $this->fileWrite($fh, ' * @param int|null   $primary_id'.PHP_EOL);
                    $this->fileWrite($fh, ' * @param array|null $defaults'.PHP_EOL);
                    $this->fileWrite($fh, ' *'.PHP_EOL);
                    $this->fileWrite($fh, ' * @return '.$cName.PHP_EOL);
                    $this->fileWrite($fh, ' */'.PHP_EOL);
                    $this->fileWrite($fh,
                        'public function get'.$fName.'(int $primary_id = null, array $defaults = null)'.PHP_EOL);
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
                    // Check to see if we have a primary in the args.
                    $primary_name = null;
                    $primary_arg  = false;
                    if(defined($cName.'::CLASS_PRIMARY')) {
                        $primary_name = constant($cName.'::CLASS_PRIMARY');
                    }

                    foreach ($definition['factory'] as $method => $methodArgs) {
                        $this->fileWrite($fh, PHP_EOL);
                        $this->fileWrite($fh, '/**'.PHP_EOL);
                        $maxLeft   = 8;
                        $variables = [];
                        foreach ($methodArgs as $methodArg) {
                            $tmp         = explode(' ', $methodArg);
                            $maxLeft     = max($maxLeft, strlen($tmp[0]));
                            $variables[] = $tmp[1];
                            if($primary_name != null && $tmp[1] == '$'.$primary_name) {
                                $primary_arg = true;
                            }
                        }
                        if(!$primary_arg) {
                            $this->fileWrite($fh,
                                ' * @param '.str_pad('int|null', $maxLeft, ' ', STR_PAD_RIGHT).' $primary_id'.PHP_EOL);
                        }
                        foreach ($methodArgs as $methodArg) {
                            $tmp    = explode(' ', $methodArg);
                            $tmp[0] = str_pad($tmp[0], $maxLeft, ' ', STR_PAD_RIGHT);
                            $this->fileWrite($fh, ' * @param '.implode(' ', $tmp).PHP_EOL);
                        }
                        $this->fileWrite($fh, ' *'.PHP_EOL);
                        $this->fileWrite($fh, ' * @return '.$cName.PHP_EOL);
                        $this->fileWrite($fh, ' */'.PHP_EOL);

                        $tmp = explode('\\',$cName);
                        $translatedMethod = array_pop($tmp).str_replace('factory','By',$method);
                        if(!$primary_arg && !in_array('int $primary_id',$methodArgs)) {
                            array_unshift($variables, '$primary_id');
                            array_unshift($methodArgs, 'int $primary_id = 0');
                        }

                        $this->fileWrite($fh, 'public function get'.$translatedMethod.'('.implode(', ',$methodArgs).')'.PHP_EOL);
                        $this->fileWrite($fh, '{'.PHP_EOL);
                        $this->fileBump($fh);
                        if (array_key_exists('gc', $definition) && $definition['gc'] > 0) {
                            $this->fileWrite($fh,
                                '$this->garbageCollect(\''.$cName.'\', '.$definition['gc'].');'.PHP_EOL.PHP_EOL);
                        }
                        if($primary_arg && count($variables) == 1) {
                            $variables = array_values($variables);
                            $variables[] = $variables[0];
                        }
                        $this->fileWrite($fh, 'return $this->getFactoryObject(\''.$cName.'\', \''.$method.'\', false, '.implode(', ', $variables).');'.PHP_EOL);
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
     * @return mixed
     */
    protected function fileOpen(string $filename)
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
    protected function fileWrite($key, string $string)
    {
        fwrite($this->open_files[$key], str_repeat(' ', $this->open_files_level[$key] * 4).$string);
    }

    /**
     * @param mixed $key
     * @return void
     */
    protected function fileBump($key)
    {
        ++$this->open_files_level[$key];
    }

    /**
     * @param mixed $key
     * @return void
     */
    protected function fileDrop($key)
    {
        --$this->open_files_level[$key];
    }

    /**
     * @param mixed $key
     * @return void
     */
    protected function fileClose($key)
    {
        fclose($this->open_files[$key]);
        unset($this->open_files[$key]);
        unset($this->open_files_level[$key]);
    }


    /**
     * @return array
     */
    private function generateAnnotatedConfig()
    {
        $return = [];

        if (count($this->paths) > 0) {
            foreach ($this->paths as $path) {
                $classFiles = self::glob_recursive(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.php');
                foreach ($classFiles as $file) {
                    $namespace = '';
                    $className = '';
                    $fh        = fopen($file, 'r');
                    while (($buffer = fgets($fh)) !== false) {
                        if (substr($buffer, 0, 9) == 'namespace') {
                            $namespace = substr(trim($buffer), 10, -1);
                        }
                        if (substr($buffer, 0, 5) == 'class') {
                            $temp      = explode(' ', $buffer);
                            $className = trim($temp[1]);
                            break;
                        }
                    }
                    $classString = trim('\\'.$namespace.'\\'.$className);
                    if (class_exists($classString)) {
                        $thisClass = new \ReflectionClass($classString);
                        if (($comment = $thisClass->getDocComment()) !== false) {
                            $phpDoc = new DocBlock($comment);
                            $config = $this->processTags($classString, $phpDoc);
                            if ($config) {
                                $return[$className] = $config;
                            }
                        }
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @param string                             $classString
     * @param \phpDocumentor\Reflection\DocBlock $phpDoc
     * @return array|bool
     */
    private function processTags($classString, DocBlock $phpDoc)
    {
        if (!$phpDoc->hasTag('om-method')) {
            return false;
        }

        $tmp = explode('\\',trim($classString));

        $config = [
            'method'    => $phpDoc->getTagsByName('om-method')[0]->getContent(),
            'name'      => array_pop($tmp),
            'namespace' => implode('\\',$tmp),
            'gc'        => 0,
        ];
        unset($tmp);

        if($phpDoc->hasTag('om-name')) {
            $config['name'] = trim($phpDoc->getTagsByName('om-name')[0]->getContent());
        }
        if($phpDoc->hasTag('om-namespace')) {
            $config['namespace'] = trim($phpDoc->getTagsByName('om-namespace')[0]->getContent());
        }
        if($phpDoc->hasTag('om-gc')) {
            $config['gc'] = abs(intval(trim($phpDoc->getTagsByName('om-gc')[0]->getContent())));
        }

        $factory = [];
        if(in_array($config['method'],['getFactoryObject','getFactoryModelObject'])) {
            // Handle factory stuff
            $i = 0;
            while($i < 1000) {
                ++$i;
                $method = $phpDoc->getTagsByName('om-factory-'.$i.'-method');
                if(!$method) {
                    break;
                }
                $methodName = $method[0]->getContent();
                $methodArgs = [];
                $args       = $phpDoc->getTagsByName('om-factory-'.$i.'-arg');
                foreach($args as $arg) {
                    $methodArgs[] = $arg->getContent();
                }

                $factory[$methodName] = $methodArgs;
            }
        }
        $config['factory'] = $factory;

        return $config;
    }
}
