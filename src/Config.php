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
        $file  = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR;
        $file .= 'config'.DIRECTORY_SEPARATOR.'config.yml';
        if (!file_exists($file)) {
            throw new Exception('Config File Not Found');
        }

        $config = Yaml::parseFile($file);
        if (isset($config['config_path'])) {
            $file   = __DIR__.DIRECTORY_SEPARATOR.$config['config_path'];
            $config = Yaml::parseFile($file);
        }

        // Get the example config, make sure we have all variables.
        $example  = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR;
        $example .= 'config/config.example.yml';
        $exConfig = Yaml::parseFile($example);

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
    public function getConfig(): array
    {
        return $this->config;
    }
}
