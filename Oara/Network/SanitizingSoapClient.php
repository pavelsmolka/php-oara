<?php

namespace Oara\Network;

class SanitizingSoapClient extends \SoapClient{

    public function __doRequest($req, $location, $action, $version = SOAP_1_1){

        $xml = parent::__doRequest($req, $location, $action, $version);

        $xml = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $xml);

        return $xml;

    }

}