<?php

if (!function_exists('curl_init')) {
    throw new Lengow_Sync_Model_Connector_Exception('Lengow needs the CURL PHP extension.', -1);
}
if (!function_exists('json_decode')) {
    throw new Lengow_Sync_Model_Connector_Exception('Lengow needs the JSON PHP extension.', -2);
}
if (!function_exists('simplexml_load_string')) {
    throw new Lengow_Sync_Model_Connector_Exception('Lengow needs the SIMPLE XML PHP extension.', -3);
}

/**
 * Lengow Sync model connector
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Model_Connector
{

    const VERSION = '1.0';

    public $error;

    protected $access_token;

    protected $secret;

    protected $token;

    protected $account_id;

    protected $user_id;

    protected $request;

    const LENGOW_API_URL = 'http://api.lengow.io:80';

    const LENGOW_API_SANDBOX_URL = 'http://api.lengow.net:80';
    
    
    /**
     * Default options for curl.
     */
    public static $CURL_OPTS = array(
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => 'lengow-php-sdk',
    );

    /**
     * Make a new Lengow API Connector.
     *
     * @param varchar $access_token Your access token.
     * @param varchar $secret Your secret.
     */
    public function init($access_token, $secret)
    {
        $this->access_token = $access_token;
        $this->secret = $secret;
    }

    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Connectection to the API
     *
     * @param varchar $user_token The user token if is connected
     *
     * @return mixed array [authorized token + account_id + user_id] or false
     */
    public function connect($user_token = '')
    {
        $data = $this->_callAction(
            '/access/get_token',
            array(
                'access_token' => $this->access_token,
                'secret'       => $this->secret,
                'user_token'   => $user_token
            ),
            'POST'
        );
        if (isset($data['token'])) {
            $this->token = $data['token'];
            $this->account_id = $data['account_id'];
            $this->user_id = $data['user_id'];
            return $data;
        } else {
            return false;
        }
    }

    /**
     * The API method.
     *
     * @param varchar $method Lengow method API call.
     * @param varchar $array Lengow method API parameters
     * @param varchar $type type of request GET|POST|PUT|HEAD|DELETE|PATCH
     * @param varchar $format return format of API
     *
     * @return array The formated data response
     */
    public function call($method, $array = array(), $type = 'GET', $format = 'json')
    {
        $this->connect();
        try {
            if (!array_key_exists('account_id', $array)) {
                $array['account_id'] = $this->account_id;
            }
            $data = $this->_callAction($method, $array, $type, $format);
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return $data;
    }

    public function get($method, $array = array(), $format = 'json')
    {
        return $this->call($method, $array, 'GET', $format);
    }

    public function post($method, $array = array(), $format = 'json')
    {
        return $this->call($method, $array, 'POST', $format);
    }

    public function head($method, $array = array(), $format = 'json')
    {
        return $this->call($method, $array, 'HEAD', $format);
    }

    public function put($method, $array = array(), $format = 'json')
    {
        return $this->call($method, $array, 'PUT', $format);
    }

    public function delete($method, $array = array(), $format = 'json')
    {
        return $this->call($method, $array, 'DELETE', $format);
    }

    public function patch($method, $array = array(), $format = 'json')
    {
        return $this->call($method, $array, 'PATCH', $format);
    }

    private function _callAction($api, $args, $type, $format = 'json')
    {
        $result = $this->_makeRequest($type, self::LENGOW_API_URL . $api, $args, $this->token);
        return $this->_format($result, $format);
    }

    private function _format($data, $format)
    {
        switch ($format) {
            case 'json':
                return json_decode($data, true);
            case 'csv':
                return $data;
            case 'xml':
                return simplexml_load_string($data);
            case 'stream':
                return $data;
        }
    }

    protected function _makeRequest($type, $url, $args, $token)
    {
        $ch = curl_init();
        // Options
        $opts = self::$CURL_OPTS;
        $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($type);
        $url = parse_url($url);
        $opts[CURLOPT_PORT] = $url['port'];
        $opts[CURLOPT_HEADER] = true;
        $opts[CURLOPT_RETURNTRANSFER] = true;
        $opts[CURLOPT_VERBOSE] = false;
        if (isset($token)) {
            $opts[CURLOPT_HTTPHEADER] = array(
                'Authorization: '.$token
            );
        }
        $url = $url['scheme'].'://'.$url['host'].$url['path'];
        switch ($type) {
            case "GET":
                $opts[CURLOPT_URL] = $url.'?'.http_build_query($args);
                Mage::helper('lensync/data')->log('Connector: '.$opts[CURLOPT_URL]);
                break;
            case "PATCH":
                if (isset($token)) {
                    $opts[CURLOPT_HTTPHEADER] = array_merge(
                        $opts[CURLOPT_HTTPHEADER],
                        array('Content-Type: application/json')
                    );
                }
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = count($args);
                $opts[CURLOPT_POSTFIELDS] = json_encode($args);
                break;
            default:
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = count($args);
                $opts[CURLOPT_POSTFIELDS] = http_build_query($args);
                break;
        }
        // Exectute url request
        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);
        $error = curl_errno($ch);
        list($header, $data) = explode("\r\n\r\n", $result, 2);
        $information = curl_getinfo($ch, CURLINFO_HEADER_OUT);
        curl_close($ch);
        if ($data === false) {
            throw new Exception('Bad request '.$error['code']);
            return false;
        }
        return $data;
    }

    public function getAccountId()
    {
        return $this->account_id;
    }

    /**
     * Check API Authentification
     *
     * @param integer $account_id Account id
     *
     * @return boolean
     */
    public function isValidAuth($account_id)
    {
        $result = $this->connect();
        if (isset($result['token']) && $account_id != 0 && is_integer($account_id)) {
            return true;
        } else {
            return false;
        }
    }
}
