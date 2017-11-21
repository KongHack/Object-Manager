<?php
namespace GCWorld\ObjectManager;

use Composer\Script\Event;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ComposerInstaller
 * @package GCWorld\ORM
 */
class ComposerInstaller
{
    /**
     * @param \Composer\Script\Event $event
     * @return bool
     */
    public static function setupConfig(Event $event)
    {
        $ds        = DIRECTORY_SEPARATOR;
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $myDir     = dirname(__FILE__);

        // Determine if ORM ini already exists.
        $iniPath = realpath($vendorDir.$ds.'..'.$ds.'config').$ds;

        if (!is_dir($iniPath)) {
            @mkdir($iniPath);
            if (!is_dir($iniPath)) {
                echo 'WARNING:: Cannot create config folder in application root:: '.$iniPath;
                return false;   // Silently Fail.
            }
        }
        if (!file_exists($iniPath.'GCWorld_ObjectManager.yml')) {
            $example = file_get_contents($myDir.$ds.'..'.$ds.'config'.$ds.'config.example.yml');
            file_put_contents($iniPath.'GCWorld_ObjectManager.yml', $example);
        }

        $config = ['config_path' => $iniPath.'GCWorld_ObjectManager.yml'];
        file_put_contents($myDir.$ds.'..'.$ds.'config'.$ds.'config.yml', Yaml::dump($config));
        return true;
    }
}
