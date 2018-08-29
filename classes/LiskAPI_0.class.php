<?php

class LiskAPI0 {
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
                $server->version = Config::API_VERSION_1;
                Logger::log (Logger::INFO, "[{$server->name}] Switched API version: {$server->version}");
            }
            return false;
        }
        $json = json_decode($result);
        if (!isset ($json) || !isset ($json->success) || !$json->success) {
            return false;
        }
        Logger::log (Logger::DEBUG, "[{$server->name}] " . $result);
        return $json;
    }

    public function getStatus ($server) {
        $protocol = $server->ssl ? 'https' : 'http';
        $url = "{$protocol}://{$server->ip}:{$server->port}/api/loader/status/sync";
        $this->_curl->setPost (null);
        $this->_curl->setUrl ($url);
        return $this->_send_request ($server);
    }

    public function getForgingStatus ($server, $publicKey) {
        $protocol = $server->ssl ? 'https' : 'http';
        $url = "{$protocol}://{$server->ip}:{$server->port}/api/delegates/forging/status?publicKey={$publicKey}";
        $this->_curl->setPost (null);
        $this->_curl->setUrl ($url);
        return $this->_send_request ($server);
    }

    public function disableForging ($server, $secret) {
        $protocol = $server->ssl ? 'https' : 'http';
        $url = "{$protocol}://{$server->ip}:{$server->port}/api/delegates/forging/disable";
        $post = json_encode ([
            'secret' => $secret
        ]);

        $this->_curl->setUrl ($url);
        $this->_curl->setPost ($post);
        return $this->_send_request ($server);
    }

    public function enableForging ($server, $secret) {
        $protocol = $server->ssl ? 'https' : 'http';
        $url = "{$protocol}://{$server->ip}:{$server->port}/api/delegates/forging/enable";
        $post = json_encode ([
            'secret' => $secret
        ]);

        $this->_curl->setUrl ($url);
        $this->_curl->setPost ($post);
        return $this->_send_request ($server);
    }
}

?>
