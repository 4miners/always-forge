<?php
Logger::getInstance ();

class Logger {
    const FATAL   = 'FATAL';
    const SYNTAX  = 'SYNTAX';
    const WARNING = 'WARNING';
    const INFO    = 'INFO';
    const DEBUG   = 'DEBUG';
    const OK      = 'OK';

    private static $_instance;
    private static $_mode;

    private function __construct () {
        if(php_sapi_name() == 'cli' || empty($_SERVER['REMOTE_ADDR'])) {
            self::$_mode = 'shell';
        } else {
            self::$_mode = 'web';
            header ("Content-Type: text/html; charset=utf-8");
            ob_implicit_flush (true);
            ob_end_flush ();
        }
    }

    public static function log ($level, $log) {
        if (Config::$logger_level === Config::LOGGER_LEVEL_NONE) { return; }
        if (Config::$logger_level === Config::LOGGER_LEVEL_INFO && $level === self::DEBUG) { return; }

        if (Config::$logger_level === Config::LOGGER_LEVEL_DEBUG) {
            $bt = debug_backtrace();
            $caller = '';
            if (isset ($bt[1]) && is_array($bt[1])) {
                if (isset ($bt[1]['class']) && isset ($bt[1]['function']) && isset ($bt[1]['type'])) {
                    $caller = "[{$bt[1]['class']}{$bt[1]['type']}{$bt[1]['function']}]";
                }
            }

            if (!empty($caller)) {
                $log = "{$caller} {$log}";
            }
        }

        $log = (!empty ($level) ? "{$level} {$log}" : $log);

        if (self::$_mode == 'web') {
            switch ($level) {
                case self::FATAL:
                case self::SYNTAX:
                    $color = 'red'; break;
                case self::WARNING:
                    $color = 'orange'; break;
                case self::INFO:
                    $color = (Config::$logger_level === Config::LOGGER_LEVEL_INFO ? 'grey' : 'blue'); break;
                case self::DEBUG:
                    $color = 'grey'; break;
                case self::OK:
                    $color = 'green'; break;
                default:
                    $color = null; break;
            }

            $log = date ('Y-m-d H:i:s', time()) . ' ' . (!is_null($color) ? "<font color=\"{$color}\">{$log}</font><br />\n" : "{$log}<br />\n");
        } else {
            $log = date ('Y-m-d H:i:s', time()) . " {$log}\n";
        }

        echo $log;
    }

    public static function getInstance () {
        if (!isset (self::$_instance)) {
            self::$_instance = new Logger ();
        }
        return self::$_instance;
    }
}

?>
