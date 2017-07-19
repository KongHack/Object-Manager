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

    public function __construct()
    {
        $this->master_location  = __DIR__;

        $cConfig      = new Config();
        $config       = $cConfig->getConfig();
        $this->config = $config;
        $paths        = [];

        foreach($this->config as $model => $definition) {
            // Step 1, eliminate examples
            if(strpos($model,'Object:ExampleModelName')===0) {
                unset($this->config[$model]);
                continue;
            }

            // Step 2, handle paths
            if($model == 'Paths') {
                unset($definition['Example1']);
                unset($definition['Example2']);
                $paths = array_values($definition);
                unset($this->config[$model]);
                continue;
            }

            // Step 3, eliminate remaining garbage
            if(strpos($model,'Object:')==false) {
                unset($this->config[$model]);
                continue;
            }

            // Remove the "Object:" part
            unset($this->config[$model]);
            $this->config[substr($model,7)] = $definition;
        }

        if(count($paths) > 0) {
            $extra = $this->generateAnnotatedConfig($paths);
            // We are using this order so that the flat file will override
            $this->config = array_merge($extra, $this->config);
        }

        // Make sure we have trailing slashes!
        foreach($this->config as &$definition) {
            if(array_key_exists('namespace',$definition) && strpos($definition['namespace'],-1) != '\\') {
                $definition['namespace'] .= '\\';
            }
        }

    }

    /**
     * @return bool
     */
    public function generate()
    {
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
            if(!array_key_exists('function', $definition)) {
                continue;
            }

            $name = $definition['namespace'].$model;

            switch($definition['function']) {
                case 'getModel':
                    $this->fileWrite($fh, PHP_EOL);
                    $this->fileWrite($fh, '/**'.PHP_EOL);
                    $this->fileWrite($fh, ' * @param int|null   $primary_id'.PHP_EOL);
                    $this->fileWrite($fh, ' * @param array|null $defaults'.PHP_EOL);
                    $this->fileWrite($fh, ' *'.PHP_EOL);
                    $this->fileWrite($fh, ' * @return '.$name.PHP_EOL);
                    $this->fileWrite($fh, ' */'.PHP_EOL);
                    $this->fileWrite($fh, 'public function get'.$model.'(int $primary_id = null, array $defaults = null)'.PHP_EOL);
                    $this->fileWrite($fh, '{'.PHP_EOL);
                    $this->fileBump($fh);
                    if(array_key_exists('gc',$definition) && $definition['gc'] > 0) {
                        $this->fileWrite($fh, '$this->garbageCollect(\''.$name.'\', '.$definition['gc'].');'.PHP_EOL.PHP_EOL);
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
                    $this->fileWrite($fh, ' * @return '.$name.PHP_EOL);
                    $this->fileWrite($fh, ' */'.PHP_EOL);
                    $this->fileWrite($fh, 'public function get'.$model.'(int $primary_id = null, array $defaults = null)'.PHP_EOL);
                    $this->fileWrite($fh, '{'.PHP_EOL);
                    $this->fileBump($fh);
                    if(array_key_exists('gc',$definition) && $definition['gc'] > 0) {
                        $this->fileWrite($fh, '$this->garbageCollect(\''.$name.'\', '.$definition['gc'].');'.PHP_EOL.PHP_EOL);
                    }
                    $this->fileWrite($fh, 'return $this->getObject(\''.$name.'\', $primary_id, $defaults);'.PHP_EOL);
                    $this->fileDrop($fh);
                    $this->fileWrite($fh, '}'.PHP_EOL);
                    $this->fileWrite($fh, PHP_EOL);
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
    private function generateAnnotatedConfig($paths)
    {
        $return = [];

        if (count($paths) > 0) {
            foreach ($paths as $path) {
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
                            $className = $temp[1];
                            break;
                        }
                    }
                    $classString = trim('\\'.$namespace.'\\'.$className);
                    if (class_exists($classString)) {
                        $thisClass = new \ReflectionClass($classString);
                        if (($comment = $thisClass->getDocComment()) !== false) {
                            $phpDoc = new DocBlock($comment);
                            $config = self::processTags($classString, $phpDoc);
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
    private static function processTags($classString, DocBlock $phpDoc)
    {
        if (!$phpDoc->hasTag('om-method')) {
            return false;
        }

        $tmp = explode('\\',$classString);

        $config = [
            'method'    => $phpDoc->getTagsByName('om-method'),
            'name'      => array_pop($tmp),
            'namespace' => implode('\\',$tmp),
            'gc'        => 0,
        ];
        unset($tmp);

        if($phpDoc->hasTag('om-name')) {
            $config['name'] = $phpDoc->getTagsByName('om-name');
        }
        if($phpDoc->hasTag('om-namespace')) {
            $config['namespace'] = $phpDoc->getTagsByName('om-namespace');
        }
        if($phpDoc->hasTag('om-gc')) {
            $config['gc'] = abs(intval($phpDoc->getTagsByName('om-gc')));
        }

        return $config;
    }
}
