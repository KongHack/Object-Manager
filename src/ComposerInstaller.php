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
    const CONFIG_FILE_NAME = 'GCWorld_ObjectManager.yml';

    /**
     * @param \Composer\Script\Event $event
     * @return bool
     */
    public static function setupConfig(Event $event)
    {
        $ds        = DIRECTORY_SEPARATOR;
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $myDir     = __DIR__;

        // Determine if ORM yml already exists.
        $ymlPath = realpath($vendorDir.$ds.'..'.$ds.'config').$ds;

        if (!is_dir($ymlPath)) {
            @mkdir($ymlPath);
            if (!is_dir($ymlPath)) {
                echo 'WARNING:: Cannot create config folder in application root:: '.$ymlPath;
                return false;   // Silently Fail.
            }
        }
        if (!file_exists($ymlPath.self::CONFIG_FILE_NAME)) {
            $example = file_get_contents($myDir.$ds.'..'.$ds.'config'.$ds.'config.example.yml');
            file_put_contents($ymlPath.self::CONFIG_FILE_NAME, $example);
        }


        $tmpYml = explode(DIRECTORY_SEPARATOR, $ymlPath);
        $tmpMy  = explode(DIRECTORY_SEPARATOR, $myDir);
        $loops  = max(count($tmpMy),count($tmpYml));

        array_pop($tmpYml); // Remove the trailing slash

        for($i=0;$i<$loops;++$i) {
            if(!isset($tmpYml[$i]) || !isset($tmpMy[$i])) {
                break;
            }
            if($tmpYml[$i] === $tmpMy[$i]) {
                unset($tmpYml[$i]);
                unset($tmpMy[$i]);
            }
        }

        $relPath = str_repeat('..'.DIRECTORY_SEPARATOR,count($tmpMy));
        $relPath .= implode(DIRECTORY_SEPARATOR, $tmpYml);
        $ymlPath = $relPath.DIRECTORY_SEPARATOR.self::CONFIG_FILE_NAME;

        $config = ['config_path' => $ymlPath];
        file_put_contents($myDir.$ds.'..'.$ds.'config'.$ds.'config.yml', Yaml::dump($config));
        return true;
    }
}
