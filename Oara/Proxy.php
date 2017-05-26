<?php

namespace Oara;

class Proxy
{
    private $host;
    private $port;
    private $username;
    private $password;
    
    /**
     * @param string $host
     * @param integer $port
     * @param string $username
     * @param string $password
     */
    public function __construct($host, $port, $username, $password) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }
    
    /**
     * @return array
     */
    public function asSoapOptions() {
        return [
            'proxy_host' => $this->host,
            'proxy_port' => $this->port,
            'proxy_login'    => $this->username,
            'proxy_password' => $this->password
        ];
    }
    
}