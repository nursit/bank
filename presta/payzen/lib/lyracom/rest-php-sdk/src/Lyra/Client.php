<?php
namespace Lyra;

use Lyra\Exceptions\LyraException;
use Lyra\Constants;

class Client
{
    private static $_defaultUsername = null;
    private static $_defaultPassword = null;
    private static $_defaultPublicKey = null;
    private static $_defaultProxyHost = null;
    private static $_defaultProxyPort = null;
    private static $_defaultEndpoint = null;
    private static $_defaultClientEndpoint = null;
    private static $_defaultHashKey = null;

    private $_username = null;
    private $_password = null;
    private $_publicKey = null;
    private $_connectionTimeout = 45;
    private $_timeout = 45;
    private $_proxyHost = null;
    private $_proxyPort = null;
    private $_endpoint = null;
    private $_clientEndpoint = null;
    private $_hashKey = null;
    private $_lastCalculatedHash = null;

    public function __construct() {
        /* Assign default values */
        $this->_username = self::$_defaultUsername;
        $this->_password = self::$_defaultPassword;
        $this->_publicKey = self::$_defaultPublicKey;
        $this->_proxyHost = self::$_defaultProxyHost;
        $this->_proxyPort = self::$_defaultProxyPort;
        $this->_endpoint = self::$_defaultEndpoint;
        $this->_clientEndpoint = self::$_defaultClientEndpoint;
        $this->_hashKey = self::$_defaultHashKey;
    }

    public static function resetDefaultConfiguration() {
        self::$_defaultUsername = null;
        self::$_defaultPassword = null;
        self::$_defaultPublicKey = null;
        self::$_defaultProxyHost = null;
        self::$_defaultProxyPort = null;
        self::$_defaultEndpoint = null;
        self::$_defaultClientEndpoint = null;
        self::$_defaultHashKey = null;
    }

    public function getVersion() {
        return Constants::SDK_VERSION;
    }

    public static function setDefaultEndpoint($endpoint) {
        static::$_defaultEndpoint = $endpoint;
    }

    public function setEndpoint($endpoint) {
         $this->_endpoint = $endpoint;
    }

    public function getEndpoint() {
        return $this->_endpoint;
    }

    public static function setDefaultClientEndpoint($clientEndpoint) {
        static::$_defaultClientEndpoint = $clientEndpoint;
    }

    public function setClientEndpoint($clientEndpoint) {
        $this->_clientEndpoint = $clientEndpoint;
    }

    public function getClientEndpoint() {
        if ($this->_clientEndpoint) return $this->_clientEndpoint;
        return $this->_endpoint;
   }

    public static function setdefaultSHA256Key($defaultHashKey) {
        static::$_defaultHashKey = $defaultHashKey;
    }

    public function setSHA256Key($hashKey) {
        $this->_hashKey = $hashKey;
    }

    public function getSHA256Key() {
        return $this->_hashKey;
    }

    public static function setDefaultUsername($defaultUsername) {
        self::$_defaultUsername = $defaultUsername;
    }

    public function setUsername($username) {
        $this->_username = $username;
    }

    public function getUsername() {
        return $this->_username;
    }

    public static function setDefaultPassword($defaultPassword) {
        self::$_defaultPassword = $defaultPassword;
    }

    public function setPassword($password) {
        $this->_password = $password;
    }

    public function getPassword() {
        return $this->_password;
    }

    public static function setDefaultPublicKey($defaultPublicKey) {
        self::$_defaultPublicKey = $defaultPublicKey;
    }

    public function setPublicKey($publicKey) {
        $this->_publicKey = $publicKey;
    }

    public function getPublicKey() {
        return $this->_publicKey;
    }

    public static function setDefaultProxy($defaultHost, $defaultPort) {
        self::$_defaultProxyHost = $defaultHost;
        self::$_defaultProxyPort = $defaultPort;
    }

    public function setProxy($host, $port) {
        $this->_proxyHost = $host;
        $this->_proxyPort = $port;
    }

    public function getProxyHost() {
        return $this->_proxyHost;
    }

    public function getProxyPort() {
        return $this->_proxyPort;
    }

    public function setTimeOuts($connectionTimeout, $timeout) {
        $this->_connectionTimeout = $connectionTimeout;
        $this->_timeout = $timeout;
    }

    public function post($target, $array)
    {
        if (!$this->_username) {
            throw new LyraException("username is not defined in the SDK");
        }

        if (!$this->_password) {
            throw new LyraException("password is not defined in the SDK");
        }

        if (!$this->_endpoint) {
            throw new LyraException("REST API endpoint not defined in the SDK");
        }

        if (extension_loaded('curl')) {
            return $this->postWithCurl($target, $array);
        } else {
            return $this->postWithFileGetContents($target, $array);
        }
    }

    public function getUrlFromTarget($target)
    {
        $url = $this->_endpoint . "/api-payment/" . $target;
        $url = preg_replace('/([^:])(\/{2,})/', '$1/', $url); 
        
        return $url;
    }


    public function postWithCurl($target, $array)
    {
        $authString = $this->_username . ":" . $this->_password;
        $url = $this->getUrlFromTarget($target);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'REST PHP SDK ' . Constants::SDK_VERSION);
        curl_setopt($curl, CURLOPT_USERPWD, $authString);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($array));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT , $this->_connectionTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->_timeout);

        if($this->_proxyHost && $this->_proxyPort) {
          curl_setopt($curl, CURLOPT_PROXY, $this->_proxyHost);
          curl_setopt($curl, CURLOPT_PROXYPORT, $this->_proxyPort);
        }

        $raw_response = curl_exec($curl);

        /**
         * CA ROOT misconfigured, we try with a local CA bundle
         * It's a common error with a WAMP local server
         */
        if (curl_errno($curl) == 60 && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
            $raw_response = curl_exec($curl);
        }
                  

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $allowedCode = array(200, 401);
        $response = json_decode($raw_response , true);

        if ( !in_array($status, $allowedCode) || is_null($response)) {
            throw new LyraException("Error: call to URL $url failed with status $status, response $raw_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
        }

        return $response;
    }

    public function postWithFileGetContents($target, $array)
    {
        $url = $this->getUrlFromTarget($target);
        $authString = $this->_username . ":" . $this->_password;

        $http = array(
            'method'        => 'POST',
            'header'        => 'Authorization: Basic ' . base64_encode($authString) . "\r\n".
                              'Content-Type: application/json',
            'content'       => json_encode($array),
            'user_agent'    => 'REST PHP SDK fallback ' . Constants::SDK_VERSION,
            'timeout'       => $this->_timeout,
            'ignore_errors' => true
        );

        if($this->_proxyHost && $this->_proxyPort) {
            $http['proxy'] = $this->_proxyHost . ':' . $this->_proxyPort;
        }

        $context = stream_context_create(array('http' => $http));
        $raw_response = file_get_contents($url, false, $context);

        if (!$raw_response) {
            throw new LyraException("Error: call to URL $url failed.");
        }

        $response = json_decode($raw_response, true);

        if (!$response) {
            throw new LyraException("Error: call to URL $url failed.");
        }

        return $response;
    }

    /**
     * Retrieve payment form answer from POST data
     */
    public function getParsedFormAnswer()
    {
        if (!array_key_exists("kr-hash", $_POST)) throw new LyraException("kr-hash not found in POST parameters");
        if (!array_key_exists("kr-hash-algorithm", $_POST)) throw new LyraException("kr-hash-algorithm not found in POST parameters");
        if (!array_key_exists("kr-answer-type", $_POST)) throw new LyraException("kr-answer-type not found in POST parameters");
        if (!array_key_exists("kr-answer", $_POST)) throw new LyraException("kr-answer not found in POST parameters");

        $answer = array();
        $answer['kr-hash'] = $_POST['kr-hash'];
        $answer['kr-hash-algorithm'] = $_POST['kr-hash-algorithm'];
        $answer['kr-answer-type'] = $_POST['kr-answer-type'];
        $answer['kr-answer'] = json_decode($_POST['kr-answer'], true);

        if (is_null($answer['kr-answer'])) {
            $answer['kr-answer'] = json_decode(stripslashes($_POST['kr-answer']), true);
            if (is_null($answer['kr-answer'])) {
                if (substr(phpversion(),0 ,3) == '5.4' ) {
                    /* PHP 5.4 does not supprt json_last_* methodes */
                    $errorMessage ="kr-answer JSON decoding failed";
                } else {
                    $errorMessage ="kr-answer JSON decoding failed: " . json_last_error() . ":" . json_last_error_msg();
                }
                throw new LyraException($errorMessage);
            }
        }
        
        return $answer;
    }

    /**
     * retrieve the last calculated hash
     */
    public function getLastCalculatedHash()
    {
        return $this->_lastCalculatedHash;
    }

    /**
     * check kr-answer object signature
     */
    public function checkHash($key=NULL)
    {
        $supportedHashAlgorithm = array('sha256_hmac');

        /* check if the hash algorithm is supported */
        if (!in_array($_POST['kr-hash-algorithm'],  $supportedHashAlgorithm)) {
            throw new LyraException("hash algorithm not supported:" . $_POST['kr-hash-algorithm'] .". Update your SDK");
        }

        /* on some servers, / can be escaped */
        $krAnswer = str_replace('\/', '/', $_POST['kr-answer']);

        /* if key is not defined, we use kr-hash-key POST parameter to choose it */
        if (is_null($key)) {
            if ($_POST['kr-hash-key'] == "sha256_hmac") {
                $key = $this->_hashKey;
            } elseif ($_POST['kr-hash-key'] == "password") {
                $key = $this->_password;
            } else {
                throw new LyraException("invalid kr-hash-key POST parameter");
            }
        }
    
        $calculatedHash = hash_hmac('sha256', $krAnswer, $key);
        $this->_lastCalculatedHash = $calculatedHash;

        /* return true if calculated hash and sent hash are the same */
        if ($calculatedHash == $_POST['kr-hash']) return TRUE;

        /* some setup has some escaped chars */
        $calculatedHash = hash_hmac('sha256', stripslashes($krAnswer), $key);
        $this->_lastCalculatedHash = $calculatedHash;
        return ($calculatedHash == $_POST['kr-hash']);
    }
}