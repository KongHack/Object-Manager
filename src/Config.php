<?php
namespace GCWorld\ObjectManager;

use Exception;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Config
 * @package GCWorld\ObjectManager
 */
class Config
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * Config constructor.
     * @throws Exception
     */
    public function __construct()
    {
        $file  = rtrim(dirname(__FILE__), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR;
        $file .= 'config'.DIRECTORY_SEPARATOR.'config.yml';
        if (!file_exists($file)) {
            throw new Exception('Config File Not Found');
        }

        $config = Yaml::parse(file_get_contents($file));
        if (isset($config['config_path'])) {
            $file   = $config['config_path'];
            $config = Yaml::parse(file_get_contents($file));
        }

        // Get the example config, make sure we have all variables.
        $example  = rtrim(dirname(__FILE__), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR;
        $example .= 'config/config.example.yml';
        $exConfig = Yaml::parse(file_get_contents($example));

        $reSave = false;
        foreach ($exConfig as $k => $v) {
            if (!isset($config[$k])) {
                $config[$k] = $v;
                $reSave     = true;
            } else {
                foreach ($v as $x => $y) {
                    if (!isset($config[$k][$x])) {
                        $config[$k][$x] = $y;
                        $reSave         = true;
                    }
                }
            }
        }

        if ($reSave) {
            file_put_contents($file, Yaml::dump($config, 4));
        }

        $this->config = $config;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }
}
