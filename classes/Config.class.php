<?php
Config::getInstance ();

class Config {

    const LOGGER_LEVEL_DEBUG = 'LOGGER_LEVEL_DEBUG';
    const LOGGER_LEVEL_INFO  = 'LOGGER_LEVEL_INFO';
    const LOGGER_LEVEL_NONE  = 'LOGGER_LEVEL_NONE';

    private static $_instance;
    public static $dir, $logger_level;

    private function __construct () {
        self::$dir = array (
            'MAIN_DIRECTORY'    => realpath (dirname (dirname (__FILE__))) . '/',
            'CLASSES_DIRECTORY' => realpath (dirname (__FILE__)) . '/'
        );

        self::$logger_level = self::LOGGER_LEVEL_DEBUG;
    }

    public static function getInstance () {
        if (!isset (self::$_instance)) {
            self::$_instance = new Config ();
        }
        return self::$_instance;
    }
}

?>
