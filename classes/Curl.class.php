<?php

class CurlException extends Exception {
    static public function isError (&$data, &$curl) {
        if ($data === false) {
            if ($errno = curl_errno ($curl->_ch)) {
                $error_message = curl_error ($curl->_ch);
            }

            throw new self ("CURL_ERROR: [{$errno}]: {$error_message}", $errno);
        }
    }
}

class Curl {
    const CURL_ISOLATION_LEVEL_REQUEST = 'CURL_ISOLATION_LEVEL_REQUEST';
    const CURL_ISOLATION_LEVEL_GLOBAL  = 'CURL_ISOLATION_LEVEL_GLOBAL';

    private $_cookies, $_cookies_req, $_is_auto_cookies, $_headers, $_follow_location, $_max_redirects, $_isolation_level, $_url, $_post, $_redirect_urls;
    public $_ch;

    public function __construct ($isolation_level = self::CURL_ISOLATION_LEVEL_REQUEST) {
        # Set class isolation level
        $this->_isolation_level = $isolation_level;

        $this->_ch              = curl_init ();
        $this->_cookies         = array ();
        $this->_cookies_req     = array ();
        $this->_headers         = array ();
        $this->_follow_location = 1;
        $this->_max_redirects   = 10;
        $this->_redirect_urls   = array ();

        # Set default options
        $options = array (
            CURLOPT_HTTPHEADER           => array (
                'User-Agent: Mozilla/5.0 (Windows NT 6.2; WOW64; rv:23.0) Gecko/20100101 Firefox/23.0',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: pl,en-us;q=0.7,en;q=0.3',
                'Accept-Charset: ISO-8859-2,utf-8;q=0.7,*;q=0.7',
                // 'Keep-Alive: 115',
                'Connection: keep-alive',
                // 'Content-Type: application/x-www-form-urlencoded',
                'Pragma: no-cache',
                'Cache-Control: max-age=0'
            ),
            CURLINFO_HEADER_OUT          => true,
            CURLOPT_HEADER               => false,
            CURLOPT_HEADERFUNCTION       => array ($this, '_header_callback'), # Set callback for reading headers
            CURLOPT_RETURNTRANSFER       => true,
            CURLOPT_FOLLOWLOCATION       => false,
            CURLOPT_AUTOREFERER          => false,
            // CURLOPT_SSLVERSION           => 3,  # Force version 3 of SSL protocol
            CURLOPT_ENCODING             => '', # Enable all supported types of compression
            CURLOPT_FAILONERROR          => true,
            CURLOPT_TIMEOUT              => 30,
            CURLOPT_CONNECTTIMEOUT       => 30,
            CURLOPT_IPRESOLVE            => CURL_IPRESOLVE_V4,
            CURLOPT_DNS_USE_GLOBAL_CACHE => false,
            CURLOPT_DNS_CACHE_TIMEOUT    => 5,
            CURLOPT_SSL_VERIFYPEER       => false,
            CURLOPT_SSL_VERIFYHOST       => false,
        );
        curl_setopt_array ($this->_ch, $options);
    }

    public static function getInstance ($isolation_level = self::CURL_ISOLATION_LEVEL_REQUEST) {
        return new Curl ($isolation_level);
    }

    public function getRedirects () {
        return $this->_redirect_urls;
    }

    public function getHeaders () {
        return $this->_headers;
    }

    private function _header_callback (&$ch, &$header) {
        $this->_headers[] = $header;
        $this->_read_cookie_callback ($header);
        return strlen ($header);
    }

    private function _read_cookie_callback (&$header) {
        if (preg_match('/^Set-Cookie: (?P<cookie_name>.+?)=(?P<cookie_value>.+?)\s*(?:;|$)/i', $header, $match)) {
            $this->_cookies_req[trim ($match['cookie_name'])] = trim ($match['cookie_value']);
            Logger::log (Logger::DEBUG, "Set-Cookie: {$match['cookie_name']}={$match['cookie_value']}");
        }
    }

    public function followLocation ($follow, $max_redirects = 10) {
        $this->_follow_location = $follow;
        $this->_max_redirects   = $max_redirects;
    }

    public function exec () {
        $this->_headers     = array ();
        $this->_cookies_req = array ();
        $this->_redirect_urls = array ();

        $redirects_cnt = 0;
        do {
            $url = $this->getInfo (CURLINFO_EFFECTIVE_URL);
            Logger::log (Logger::DEBUG, ($redirects_cnt ? "[{$redirects_cnt} z {$this->_max_redirects}] {$url}" : "{$url}"));

            # If we have any cookies in array - send it
            $this->setOption (CURLOPT_COOKIE, $this->prepareCookies ());

            $result = curl_exec ($this->_ch);
            CurlException::isError ($result, $this);

            $code = $this->getInfo (CURLINFO_HTTP_CODE);
            $redirect_url = $this->getInfo (CURLINFO_REDIRECT_URL);

            if (!empty ($redirect_url)) {
                $this->_redirect_urls[] = $redirect_url;
                $this->setUrl ($redirect_url);
                $this->setPost(null);
                $this->_url = $redirect_url;
                $this->_post = null;
            }

            # If automatic cookies management is enabled - add cookies from current session to global storage
            if ($this->_is_auto_cookies) {
                $this->_cookies = array_merge ($this->_cookies, $this->_cookies_req);
            }
        } while ($this->_follow_location && !empty ($redirect_url) && ($code == 301 || $code == 302) && ++$redirects_cnt <= $this->_max_redirects);

        # After redirects close cURL session only if we work in CURL_ISOLATION_LEVEL_REQUEST mode
        if ($this->_isolation_level == self::CURL_ISOLATION_LEVEL_REQUEST) {
            curl_close ($this->_ch);
        }

        return $result;
    }

    public function getInfo ($val = null) {
        if ($val !== null) {
            return curl_getinfo ($this->_ch, $val);
        } else {
            return curl_getinfo ($this->_ch);
        }
    }

    public function setOption ($name, $val) {
        curl_setopt ($this->_ch, $name, $val);
    }

    public function getCookies () {
        return array_merge ($this->_cookies, $this->_cookies_req);
    }

    public function getCookiesRequest () {
        return $this->_cookies_req;
    }

    public function setCookies ($val) {
        $this->_cookies = $val;
    }

    public function setCookie ($name, $val) {
        $this->_cookies[$name] = $val;
    }

    private function prepareCookies () {
        $cookies = array_merge ($this->_cookies, $this->_cookies_req);
        if (empty ($cookies)) {
            return '';
        }
        foreach ($cookies as $name => &$value) {
            $value = "{$name}={$value}";
        }
        unset ($name, $value);
        return implode ('; ', array_values ($cookies));
    }

    public function clearCookies () {
        $this->_cookies     = array ();
        $this->_cookies_req = array ();
    }

    public function setAutoCookies ($val) {
        $this->_is_auto_cookies = $val;
    }

    public function setUrl ($val) {
        curl_setopt ($this->_ch, CURLOPT_URL, $val);
        $this->_url = $val;
    }

    public function setPost ($val) {
        if (empty ($val)) {
            curl_setopt ($this->_ch, CURLOPT_HTTPGET, 1);
            $this->_post = null;
        } else {
            curl_setopt ($this->_ch, CURLOPT_POST, 1);
            if (is_array ($val)) {
                curl_setopt ($this->_ch, CURLOPT_POSTFIELDS, http_build_query ($val));
                $this->_post = http_build_query ($val);
            } else {
                curl_setopt ($this->_ch, CURLOPT_POSTFIELDS, $val);
                $this->_post = $val;
            }
        }
    }

    public function setPut ($val) {
        if (empty ($val)) {
            curl_setopt ($this->_ch, CURLOPT_HTTPGET, 1);
            $this->_post = null;
        } else {
            curl_setopt ($this->_ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if (is_array ($val)) {
                curl_setopt ($this->_ch, CURLOPT_POSTFIELDS, http_build_query ($val));
                $this->_post = http_build_query ($val);
            } else {
                curl_setopt ($this->_ch, CURLOPT_POSTFIELDS, $val);
                $this->_post = $val;
            }
        }
    }
}

class CurlSingle extends Curl  {
    private static $_instance;

    public function __construct () {
        parent::__construct (self::CURL_ISOLATION_LEVEL_GLOBAL);
    }

    public static function getInstance ($reload = null) {
        if (!isset (self::$_instance) || !is_null ($reload)) {
            self::$_instance = new CurlSingle ();
        }
        return self::$_instance;
    }
}

?>
