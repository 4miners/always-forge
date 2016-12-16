<?php
    ini_set('display_errors', 'On');
    error_reporting(~E_DEPRECATED | E_STRICT);

    require_once realpath (dirname (__FILE__)) . '/classes/Config.class.php';
    require_once Config::$dir['CLASSES_DIRECTORY'] . 'Logger.class.php';
    require_once Config::$dir['CLASSES_DIRECTORY'] . 'Curl.class.php';
    require_once Config::$dir['CLASSES_DIRECTORY'] . 'LiskAPI.class.php';
    require_once Config::$dir['CLASSES_DIRECTORY'] . 'AlwaysForge.class.php';

?>
