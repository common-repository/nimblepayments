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

/**
 * Implements the Authorization header of the request to perform the identification correctly according to the type of
 * request
 */
class NimbleAPIAuthorization
{
    /**
     *
     * @var string $ token_type
     */
    private $token_type;

    /**
     *
     * @var string $ basic
     */
    private $basic;
    
    /**
     *
     * @var string $ access_token
     */
    private $access_token;

    /**
     *
     * @var string $ refresh_token
     */
    private $refresh_token;

    /**
     *
     * @var int. $ expires_in. Time in seconds for the token ceases to be valid
     */
    private $expires_in;

    /**
     *
     * @var int. $ refresh_expires_in. Time in seconds for the refresh token ceases to be valid
     */
    private $refresh_expires_in;

    /**
     *
     * @var string
     */
    private $scope;
    
    private $lang = 'es';

    /**
     * Method getBasic
     *
     * @return string
     */
    public function getBasic()
    {
        return $this->basic;
    }

    /**
     * Method setBasic
     *
     * @param string $basic
     */
    public function setBasic($basic)
    {
        $this->basic = $basic;
    }
    
    /**
     * Function addheader, add a type param with a context in the header
     *
     * @param string $param
     * @param string $content
     */
    public function addHeader($param, $content)
    {
        $this->header[$param] = $content;
    }
    
    /**
     * Function addLanguageHeader, add the Accept-Language header
     */
    public function addLanguageHeader()
    {
        $this->header['Accept-Language'] = $this->lang;
    }

    /**
     * Method clearHeader
     *
     * @param string $param
     */
    public function clearHeader()
    {
        $this->header = array();
    }


    /**
     * Method buildAccessHeader, add Access Authorization header.
     *
     * @throws Exception
     */
    public function buildAccessHeader()
    {
        if ($this->isAccessParams()) {
            $this->addHeader('Authorization', $this->token_type . ' ' . $this->access_token);
        }

    }

    /**
     * Method setAccessParams, set token_type and access_token
     *
     * @param string $response
     */
    public function setAccessParams($response)
    {
        if ((isset($response['token_type'])) && (isset($response['access_token']))) {
            $this->token_type = $response['token_type'];
            $this->access_token = $response['access_token'];
        } else {
            throw new Exception('The identification was incorrect, check clientId and clientSecret');
        }
        
        if ((isset($response['expires_in'])) && (isset($response['scope']))) {
            $this->expires_in = time() + (int)$response['expires_in'];
            $this->scope = $response['scope'];
        }
        if (isset($response['refresh_token']) && isset($response['refresh_expires_in'])) {
            $this->refresh_token = $response['refresh_token'];
            $this->refresh_expires_in = $response['refresh_expires_in'];
        }
    }

    /**
     * Method isAccessParams, return TRUE if exist the params.
     *
     * @return boolean
     */
    public function isAccessParams()
    {
        if (($this->token_type != null) && ($this->access_token != null)) {
            return true;
        }
        return false;
    }

    /**
     * Method getHeader. Returns an array of header parameters.
     *
     * @return multitype:
     */
    public function getHeader ()
    {
        return $this->header;
    }

    /**
     * Method getExpirationTime
     *
     * @return string
     */
    public function getExpirationTime()
    {
        return $this->expires_in;
    }
    /**
     * Method getRefreshExpirationTime
     *
     * @return string
     */
    public function getRefreshExpirationTime()
    {
        return $this->refresh_expires_in;
    }

    /**
     * Method getAccessToken
     *
     * @return string
     */
    public function getAccessToken()
    {
        return $this->access_token;
    }

    /**
     * Method setAccessToken
     *
     * @param string $access_token
     */
    public function setAccessToken($access_token)
    {
        $this->access_token = $access_token;
    }

    /**
     * Method getRefreshToken
     *
     * @return string
     */
    public function getRefreshToken()
    {
        return $this->refresh_token;
    }

    /**
     * Method setRefreshToken
     *
     * @param string $refresh_token
     */
    public function setRefreshToken($refresh_token)
    {
        $this->refresh_token = $refresh_token;
    }

    /**
     * Method getTokenType
     *
     * @return string
     */
    public function getTokenType()
    {
        return $this->token_type;
    }

    /**
     * Method setTokenType
     *
     * @param string $token_type
     */
    public function setTokenType($token_type)
    {
        $this->token_type = $token_type;
    }
    
    /**
     * Method buildAuthorizationHeader, add Authorization header.
     */
    public function buildAuthorizationHeader($type = 'basic')
    {
        switch ($type){
            case 'basic':
                $this->addHeader('Authorization', $this->basic);
                break;
            case 'tsec':
                $this->addHeader('Authorization', $this->token_type . ' ' . $this->access_token);
                break;
            default:
                return;
        }
    }
    
    /**
     * Function setLang
     */
    public function setLang($lang_code){
        $accepted_langs = array('es', 'en', 'de', 'it', 'pt');
        if (in_array($lang_code, $accepted_langs)){
            $this->lang = $lang_code;
        }
    }
}
