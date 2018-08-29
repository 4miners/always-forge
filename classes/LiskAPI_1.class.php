<?php

class LiskAPI1 {
    private $_curl;

    public function __construct () {
        $this->_curl = CurlSingle::getInstance ();
    }

    public function setTimeout ($timeout = 3) {
        $this->_curl->setOption (CURLOPT_TIMEOUT, $timeout);
    }

    public function setConnectionTimeout ($connection_timeout = 1000) {
        $this->_curl->setOption (CURLOPT_CONNECTTIMEOUT_MS, $connection_timeout);
    }

    private function _send_request (&$server) {
        try {
            $result = $this->_curl->exec ();
        } catch (Exception $e) {
            Logger::log (Logger::WARNING, "[{$server->name}] " . $e->getMessage ());
            if ($this->_curl->getInfo (CURLINFO_HTTP_CODE) === 404) {
                $server->version = Config::API_VERSION_0;
                Logger::log (Logger::INFO, "[{$server->name}] Switched API version: {$server->version}");
            }
            return false;
        }
        $json = json_decode($result);
        if (!isset ($json) || !isset ($json->data) || !$json->data) {
            return false;
        }
        Logger::log (Logger::DEBUG, "[{$server->name}] " . $result);
        return $json;
    }

    public function getStatus ($server) {
        $url = "http://{$server->ip}:{$server->port}/api/node/status";
        $this->_curl->setPost (null);
        $this->_curl->setUrl ($url);
        $response = $this->_send_request ($server);
        $response = $response->data;
        unset ($response->transactions);
        unset ($response->networkHeight);
        unset ($response->loaded);
        return $response;
    }

    public function getForgingStatus ($server, $publicKey) {
        $protocol = $server->ssl ? 'https' : 'http';
        $url = "{$protocol}://{$server->ip}:{$server->port}/api/node/status/forging?publicKey={$publicKey}";
        $this->_curl->setPost (null);
        $this->_curl->setUrl ($url);
        $response = $this->_send_request ($server);
        if (isset($response->data->forging) && $response->data->forging) {
            $response->enabled = true;
        }
        return $response;
    }

    public function disableForging ($server, $password, $publicKey) {
        $protocol = $server->ssl ? 'https' : 'http';
        $url = "{$protocol}://{$server->ip}:{$server->port}/api/node/status/forging";
        $put = json_encode ([
            'forging' => false,
            'password' => $password,
            'publicKey' => $publicKey
        ]);

        $this->_curl->setUrl ($url);
        $this->_curl->setPut ($put);
        return $this->_send_request ($server);
    }

    public function enableForging ($server, $password, $publicKey) {
        $protocol = $server->ssl ? 'https' : 'http';
        $url = "{$protocol}://{$server->ip}:{$server->port}/api/node/status/forging";
        $put = json_encode ([
            'forging' => true,
            'password' => $password,
            'publicKey' => $publicKey
        ]);

        $this->_curl->setUrl ($url);
        $this->_curl->setPut ($put);
        return $this->_send_request ($server);
    }
}

?>
