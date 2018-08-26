<?php

class AlwaysForge {
    private $_api, $_delegate, $_servers, $_check_interval;

    public function __construct () {
        $this->_api = (object) [
            Config::API_VERSION_0 => new LiskAPI0 (),
            Config::API_VERSION_1 => new LiskAPI1 (),
        ];
        $config = $this->_get_config ();

        # Set logger level
        if (!empty ($config->log_level) && in_array($config->log_level, array ('debug', 'info', 'none'))) {
            switch ($config->log_level) {
                case 'debug':
                    Config::$logger_level = Config::LOGGER_LEVEL_DEBUG;
                    break;
                case 'info':
                    Config::$logger_level = Config::LOGGER_LEVEL_INFO;
                    break;
                default:
                    Config::$logger_level = Config::LOGGER_LEVEL_NONE;
                    break;
            }
        } else {
            Logger::log (Logger::SYNTAX, "Invalid log level supplied");
            die;
        }

        # Set check interval
        if (!empty ($config->check_interval_sec) && is_numeric ($config->check_interval_sec)) {
            $this->_check_interval = $config->check_interval_sec;
        } else {
            Logger::log (Logger::SYNTAX, "No check interval or invalid check interval supplied");
            die;
        }

        # Set timeouts for API requests
        if (!empty ($config->timeouts->request_sec) && is_numeric ($config->timeouts->request_sec)) {
            $this->_api->{Config::API_VERSION_0}->setTimeout ($config->timeouts->request_sec);
            $this->_api->{Config::API_VERSION_1}->setTimeout ($config->timeouts->request_sec);
        }
        if (!empty ($config->timeouts->connect_msec) && is_numeric ($config->timeouts->connect_msec)) {
            $this->_api->{Config::API_VERSION_0}->setConnectionTimeout ($config->timeouts->connect_msec);
            $this->_api->{Config::API_VERSION_1}->setConnectionTimeout ($config->timeouts->connect_msec);

        }

        # Set delegate
        if (!empty ($config->delegate) && $config->delegate->address && $config->delegate->publicKey && ($config->delegate->secret || $config->delegate->password)) {
            $this->_delegate = $config->delegate;
        } else {
            Logger::log (Logger::SYNTAX, "No delegate or invalid delegate data supplied");
            die;
        }

        # Set servers
        if (!empty ($config->servers) && is_array($config->servers) && count ($config->servers)) {
            $this->_servers = $config->servers;
        } else {
            Logger::log (Logger::SYNTAX, "No servers supplied");
            die;
        }
    }

    public function start () {
        Logger::log (Logger::OK, "AlwaysForge started");
        while (true) {
            $this->_check_and_switch ();
            sleep ($this->_check_interval);
        }
    }

    public function _check_and_switch () {
        $this->_check_servers ();
        $best_servers = $this->_filter_dead_and_syncing ($this->_servers);
        $best_server = $this->_pick_best ($best_servers, null);

        $best_servers = $this->_filter_bad_heights ($best_servers);
        $best_server = $this->_pick_best ($best_servers, $best_server);

        $best_servers = $this->_filter_bad_broadhash ($best_servers);
        $best_server = $this->_pick_best ($best_servers, $best_server);

        $best_servers = $this->_filter_bad_consensus ($best_servers);
        $best_server = $this->_pick_best ($best_servers, $best_server);

        if (is_null($best_server)) {
            Logger::log (Logger::FATAL, "No suitable server for forging!");
        } else {
            Logger::log (Logger::INFO, "Best server for forging: {$best_server->name}");
            $this->_switch_forging ($best_server);
        }
    }

private function _switch_forging ($forging_server) {
    foreach ($this->_servers as $server) {
        if ($server->name !== $forging_server->name && $server->isForgingEnabled) {
            Logger::log (Logger::DEBUG, "[{$server->name}] Disabling forging...");
            if ($server->version === Config::API_VERSION_0) {
                $result = $this->_api->{$server->version}->disableForging ($server, $this->_delegate->secret);
            } else {
                $result = $this->_api->{$server->version}->disableForging ($server, $this->_delegate->password, $this->_delegate->publicKey);
            }

            if ($result) {
                Logger::log (Logger::OK, "[{$server->name}] Forging disabled");
            } else {
                Logger::log (Logger::FATAL, "[{$server->name}] Failed to disable forging");
            }
        }
    }

    if ($forging_server->isForgingEnabled) {
        Logger::log (Logger::DEBUG, "[{$forging_server->name}] Forging is already enabled");
        return true;
    }

    Logger::log (Logger::DEBUG, "[{$forging_server->name}] Enabling forging...");
    if ($forging_server->version === Config::API_VERSION_0) {
        $result = $this->_api->{$forging_server->version}->enableForging ($forging_server, $this->_delegate->secret);
    } else {
        $result = $this->_api->{$forging_server->version}->enableForging ($forging_server, $this->_delegate->password, $this->_delegate->publicKey);
    }

    if ($result) {
        Logger::log (Logger::OK, "[{$forging_server->name}] Forging enabled");
        return true;
    } else {
        Logger::log (Logger::FATAL, "[{$forging_server->name}] Failed to enable forging");
        return false;
    }
}

    private function _get_config () {
        $config_file = Config::$dir['MAIN_DIRECTORY'] . 'config.json';
        $config_str = @file_get_contents ($config_file);
        if ($config_str === false) {
            Logger::log (Logger::FATAL, "Cannot open config file: {$config_file}");
            die;
        }
        $json = json_decode ($config_str);
        if (json_last_error() !== JSON_ERROR_NONE || empty ($json)) {
            Logger::log (Logger::SYNTAX, "Cannot decode JSON config file");
            die;
        }
        return $json;
    }

    private function _check_servers () {
        foreach ($this->_servers as $key => $server) {
            if (!isset($server->version)) {
                $server->version = Config::API_VERSION_0;
                Logger::log (Logger::INFO, "[{$server->name}] Default API version: {$server->version}");
            }
            # Checking for server status
            $status = $this->_api->{$server->version}->getStatus ($server);
            $server->priority = $key;
            if ($status) {
                $server->status = $status;
                $server->isAlive = true;

                # Checking if forging is enabled on server
                $forging_status = $this->_api->{$server->version}->getForgingStatus ($server, $this->_delegate->publicKey);
                if ($forging_status) {
                    $server->isForgingEnabled = $forging_status->enabled;
                } else {
                    $server->isForgingEnabled = false;
                    $server->isAlive = false;
                }

                $log = "[{$server->name}] Alive: {$server->isAlive}, Forging: {$server->isForgingEnabled}, Pririty: {$server->priority}, Syncing: {$server->status->syncing}, Broadhash: {$server->status->broadhash}, Height: {$server->status->height}, Consensus: {$server->status->consensus}";
            } else {
                $server->isAlive = false;
                $server->isForgingEnabled = false;

                $log = "[{$server->name}] A: {$server->isAlive} F: {$server->isForgingEnabled} P: {$server->priority}";
            }
            Logger::log (Logger::INFO, $log);
        }
    }

    private function _pick_best (&$servers, $best_server) {
        $this->_sort_by_priority ($servers);
        foreach ($servers as $key => $server) {
            return $server;
        }
        return $best_server;
    }

    private function _sort_by_priority (&$servers) {
        $priorities = array ();
        foreach ($servers as $server) {
            $priorities[]  = $server->priority;
        }

        array_multisort($priorities, SORT_ASC, $servers, SORT_NUMERIC);
    }

    private function _filter_bad_consensus ($servers) {
        $best_consensus = null;
        foreach ($servers as $key => $server) {
            if ($server->status->consensus > $best_consensus) {
                $best_consensus = $server->status->consensus;
            }
        }

        Logger::log (Logger::DEBUG, "Best consensus: {$best_consensus}");

        $best_servers = array ();
        foreach ($servers as $key => $server) {
            if ($server->status->consensus === $best_consensus) {
                $best_servers[] = $server;
            }
        }
        return $best_servers;
    }

    private function _filter_bad_broadhash ($servers) {
        $broadhashes_cnt = array ();

        foreach ($servers as $key => $server) {
            if (isset ($broadhashes_cnt[$server->status->broadhash])) {
                ++$broadhashes_cnt[$server->status->broadhash];
            } else {
                $broadhashes_cnt[$server->status->broadhash] = 1;
            }
        }

        arsort ($broadhashes_cnt);
        Logger::log (Logger::DEBUG, "Broadhashes: " . json_encode ($broadhashes_cnt));
        reset ($broadhashes_cnt);
        $best_broadhash = key ($broadhashes_cnt);
        Logger::log (Logger::DEBUG, "Best broadhash: {$best_broadhash}");

        foreach ($servers as $key => $server) {
            if ($server->status->broadhash !== $best_broadhash) {
                unset ($servers[$key]);
            }
        }
        return $servers;
    }

    private function _filter_dead_and_syncing ($servers) {
        foreach ($servers as $key => $server) {
            if (!$server->isAlive || $server->status->syncing) {
                unset ($servers[$key]);
            }
        }
        return $servers;
    }

    private function _filter_bad_heights ($servers) {
        $best_height = null;
        foreach ($servers as $key => $server) {
            if ($server->status->height > $best_height) {
                $best_height = $server->status->height;
            }
        }

        Logger::log (Logger::DEBUG, "Best height: {$best_height}");

        $best_servers = array ();
        foreach ($servers as $key => $server) {
            if ($server->status->height === $best_height) {
                $best_servers[] = $server;
            }
        }
        return $best_servers;
    }

}

?>
