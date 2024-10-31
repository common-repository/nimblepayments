<?php
/**
 * Nimble-API-PHP : API v1.2
 *
 * PHP version 5.4.2
 *
 * @link https://github.com/nimblepayments/sdk-php
 * @filesource
 */

require_once 'NimbleAPIConfig.php';
require_once 'NimbleAPIAuthorization.php';
require_once 'NimbleAPIAuth.php';


/**
 * NimbleAPI is the api that does all the connection mechanism to each of the requests. It is primarily responsible for
 * handling each.
 */
class NimbleAPI
{

    /**
     * @source
     *
     * @var string $ uri. (Url service api rest)
     */
    public $base_uri;

    /**
     * @source
     *
     * @var string $ uri. (Url service api rest)
     */
    public $uri;

    /**
     * @source
     *
     * @var bool $ uri_oauth. (True if next restApiCall need oauth uri)
     */
    public $uri_oauth = false;

    /**
     *
     * @var string $ method. (Could be 'GET, POST PATH, DELETE, PUT')
     */
    public $method;

    /**
     *
     * @var array $ postfields. (Attribute Manager contain the post parameters if necessary)
     */
    private $postfields;

    /**
     *
     * @var string $ getfields. (Attribute Manager contain the get parameters if necessary)
     */
    private $getfields;

    /**
     *
     * @var Object $ authorization. (An object that contains the definition of authorization)
     */
    public $authorization;

    /**
     *
     * @var string $ laststatuscode (contain the last code for the last request: 401, 200, 500)
     */
    protected $laststatuscode;

    /**
     * 
     * @var bool $ use_curl (if need curl_lib to work)
     */
    protected $use_curl = true;

     /**
     *
     * @var string $ clientId
     */
    private $clientId;

    /**
     *
     * @var string $ clientSecret
     */
    private $clientSecret;
    
    /**
     * Method getClientId
     *
     * @return string
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * Method setClientId
     *
     * @param string $clientId
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
    }

    /**
     * Method getClientSecret
     *
     * @return string
     */
    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    /**
     * Method setClientSecret
     *
     * @param string $clientSecret
     */
    public function setClientSecret($clientSecret)
    {
        $this->clientSecret = $clientSecret;
    }
    
    /**
     * Construct method. Start the object NimbleApi. Start the Object NimbleAPIAuthorization too.
     *
     * @param array $settings. (must contain at least clientId and clientSecret vars)
     * @throws Exception. (Return exception if not exist clientId or clientSecret)
     */
    public function __construct(array $settings)
    {
        if ( $this->use_curl && ! in_array('curl', get_loaded_extensions())) {
            throw new Exception('You need to install cURL, see: http://curl.haxx.se/docs/install.html');
        }
        
        if (empty($settings['clientId']) || empty($settings['clientSecret'])) {
            throw new Exception('secretKey or clientId cannot be null or empty!');
        }

        try {
            // Set URL depending on environment
            if (NimbleAPIConfig::MODE == 'real') {
                $this->base_uri = NimbleAPIConfig::NIMBLE_API_BASE_URL;
            } else {
                $this->base_uri = NimbleAPIConfig::NIMBLE_API_BASE_URL_DEMO;
            }

            // Authenticate object
            $this->authorization = new NimbleAPIAuthorization();
            // Set credentials
            $this->setClientId($settings['clientId']);
            $this->setClientSecret($settings['clientSecret']);
            $this->authorization->setBasic('Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret));
            
            // Check if we are on oAuth process by parameter oauth_code
            if (isset($settings['oauth_code'])) {
                // oAuth process > needs to request token to security server (with oauth_code)
                NimbleAPIAuth::getCodeAuthorization($this, $settings['oauth_code']);
            } elseif (! $this->authorization->isAccessParams()) {
                // Not oAuth process > check if token is provided
                if (isset($settings['token'])) {
                    // Already authenticated > save data
                    $this->authorization->setAccessParams(array(
                        'token_type' => 'tsec',
                        'access_token' => $settings['token'],
                        ));
                    // If refresh token provided perform refresh callback
                    if (isset($settings['refreshToken'])) {
                        $this->authorization->setRefreshToken($settings['refreshToken']);
                        NimbleAPIAuth::refreshToken($this);
                    }
                } else {
                    // Not yet authenticated
                    NimbleAPIAuth::getBasicAuthorization($this);
                }
            }
        } catch (Exception $e) {
            throw new Exception('Failed to instantiate NimbleAPIAuthorization: ' . $e);
        }
    }

    /**
     * Method responsible for making Rest API calls @ Return $response from rest api.
     *
     * @return $response
     */
    public function restApiCall()
    {
        try {
            if (! isset($curl_connect)) {
                $curl_connect = curl_init();
            }
            
            $header = $this->getHeaders();
            //Prepare header
            $curl_header = array();
            foreach ($header as $param => $value) {
                if ($value != "") {
                    array_push($curl_header, $param . ': ' . $value);
                }
            }
            
            $postfields = $this->getPostfields();
            
            $url = $this->getApiUrl();

            $options = array(
                    CURLOPT_HTTPHEADER => $curl_header,
                    CURLOPT_URL => $url,
                    CURLOPT_CUSTOMREQUEST => $this->method, // GET POST PUT PATCH DELETE
                    CURLOPT_HEADER => false,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => NimbleAPIConfig::TIMEOUT
            );

            if (! is_null($postfields)) {
                $options[CURLOPT_POSTFIELDS] = $postfields;
            }
            
            curl_setopt_array($curl_connect, ($options));

            //SSL PROBLEMS
            curl_setopt($curl_connect, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl_connect, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = json_decode(curl_exec($curl_connect), true);

            $this->setLastStatusCode(curl_getinfo($curl_connect, CURLINFO_HTTP_CODE));
            
            curl_close($curl_connect);
            $this->clear();
            return $response;
        } catch (Exception $e) {
            throw new Exception('Failed to send Data in restApiCall: ' . $e);
        }
    }

    /**
     * Method setGetfields example: '?grant_type=client_credentials&scope=read'
     *
     * @param string $getfields
     * @return NimbleAPI
     */
    public function setGetfields($getfields)
    {
        $search = array(
                '#',
                ',',
                '+',
                ':'
        );
        $replace = array(
                '%23',
                '%2C',
                '%2B',
                '%3A'
        );
        
        $this->getfields = str_replace($search, $replace, $getfields);
        
        return $this;
    }

    /**
     * Method getUri
     *
     * @return string $uri
     */
    public function getUri()
    {
        return $this->uri;
    }
    /**
     * Method setUri
     *
     * @param string $uri
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
    }

    /**
     * Method setPostfields
     *
     * @param string $postfields
     */
    public function setPostfields($postfields)
    {
        $this->postfields = $postfields;
        
        return $this;
    }

    /**
     * Method getGetfields
     *
     * @return string
     */
    public function getGetfields()
    {
        return $this->getfields;
    }

    /**
     * Method getPostfields
     *
     */
    public function getPostfields()
    {
        return $this->postfields;
    }

    /**
     * Method getLastStatusCode.
     *
     *
     * @return string. Return the last status code 401 UnAuthorized, 200 Accept.
     */
    public function getLastStatusCode()
    {
        return $this->laststatuscode;
    }

    /**
     * Method setLastStatusCode
     *
     * @param unknown $code
     * @return NimbleAPI object
     */
    public function setLastStatusCode($code)
    {
        $this->laststatuscode = $code;
        return $this;
    }

    /**
     * Method clear. Clear temporal attributes after restApiCall
     */
    public function clear()
    {
        $this->uri = '';
        $this->getfields = null;
        $this->postfields = null;
        $this->authorization->clearHeader();
    }
    
    /**
     * Method getHeaders
     * @return array. Returns the header to the api rest call
     */
    public function getHeaders ()
    {
        if ($this->uri_oauth){
            $this->authorization->buildAuthorizationHeader('basic');
        } else{
            $this->authorization->buildAuthorizationHeader('tsec');
        }
        $header = $this->authorization->getHeader();
        return $header;
    }
    
    /**
     * Methos getApiUrl
     * @return string. Return the url to the api rest call
     */
    function getApiUrl(){
        
        if ($this->uri_oauth) {
            $url = NimbleAPIConfig::OAUTH_URL;
            $this->uri_oauth = false;
        } else {
            $url = $this->base_uri . $this->uri;
        }
        
        //Set GET params
        if ( $this->getfields ){
            $getfields = $this->getGetfields();
            if ($getfields !== '') {
                $url .= $getfields;
            }
        }
        
       return $url;
    }
    
    /*
     * Get the URL for Authentication on 3 steps
     */
    public function getOauth3Url(){
        $params = array(
            'response_type' => 'code',
            'client_id' => $this->getClientId()
        );
        return NimbleAPIConfig::OAUTH3_URL_AUTH.'?'.http_build_query($params);
    }
    
    /*
     * Get gateway url
     */
    static public function getGatewayUrl($platform, $storeName, $storeURL, $redirectURL) {
        $params = array(
            'action' => 'gateway',
            'mode' => NimbleAPIConfig::MODE,
            'platform' => $platform,
            'storeName' => $storeName,
            'storeURL' => rtrim(strtr(base64_encode($storeURL), '+/', '-_'), '='),
            'redirectURL' => rtrim(strtr(base64_encode($redirectURL), '+/', '-_'), '=')
            
        );
        
        return NimbleAPIConfig::GATEWAY_URL.'?'.http_build_query($params);
        
    }
    
    /*
     * Get OTP url
     */
    static public function getOTPUrl($ticket, $back_url) {
        $params = array(
            'ticket' => $ticket,
            'back_url' => $back_url
            
        );
        
        return NimbleAPIConfig::OTP_URL.'?'.http_build_query($params);
        
    }
    
    public function changeDefaultLanguage($lang_code){
        $this->authorization->setLang($lang_code);
    }
}
