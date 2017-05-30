<?php

namespace Oara;

class Proxy
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $usesSSL;
    
    /**
     * @param string $host
     * @param integer $port
     * @param string $username
     * @param string $password
     */
    public function __construct($host, $port, $username, $password) {
        $this->usesSSL = (in_array(parse_url($host, PHP_URL_SCHEME), ['https', 'ssl'])) ? true : false;
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }
    
    /**
     * @return array
     */
    public function asSoapOptions() {
        
        $options = [];
        
        if ($this->usesSSL) {
            $context = stream_context_create( [
                'http'=> [
                    'proxy'=>'tcp://'.$this->host.':'.$this->port
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            $options['stream_context'] = $context;
        }
        
        return $options + [
            'proxy_host' => $this->host,
            'proxy_port' => $this->port,
            'proxy_login'    => $this->username,
            'proxy_password' => $this->password
        ];
    }
    
    /**
     * @return array
     */
    public function asCurlOptions() {
        return [
            CURLOPT_PROXY           => $this->host,
            CURLOPT_PROXYPORT       => $this->port,
            CURLOPT_PROXYUSERPWD    => (empty($this->username)) ? null : $this->username .':'. $this->password
        ];
    }
    
    /**
     * @return array
     */
    public function asContextOptions() {
        $context = [
            'http' => ['proxy' => $this->host.':'.$this->port]
        ];
        
        if ($this->username) {
            $auth = base64_encode($this->username.':'.$this->password);
            $context['http']['header'] = 'Proxy-Authorization: Basic '.$auth;
        }
        
        return $context;
    }
    
}