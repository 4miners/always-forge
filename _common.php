<?php
    ini_set('display_errors', 'On');
    if (!ini_get('date.timezone')) {
        date_default_timezone_set('UTC');
    }
    error_reporting(~E_DEPRECATED | E_STRICT);

    require_once realpath (dirname (__FILE__)) . '/classes/Config.class.php';
    require_once Config::$dir['CLASSES_DIRECTORY'] . 'Logger.class.php';
    require_once Config::$dir['CLASSES_DIRECTORY'] . 'Curl.class.php';
    require_once Config::$dir['CLASSES_DIRECTORY'] . 'LiskAPI_0.class.php';
    require_once Config::$dir['CLASSES_DIRECTORY'] . 'LiskAPI_1.class.php';
    require_once Config::$dir['CLASSES_DIRECTORY'] . 'AlwaysForge.class.php';

?>
