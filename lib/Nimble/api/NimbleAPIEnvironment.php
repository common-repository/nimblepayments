<?php
require_once(dirname(__FILE__).'/../base/NimbleAPIConfig.php');

class NimbleAPIEnvironment
{

    /**
     * It allows to verify the environment the API_clientId and clientSecret application credentials belongs to.
     * 
     * @param type $NimbleApi
     * @return type
     * @throws Exception
     */
    public static function verification($NimbleApi)
    {
        
        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty.');
        }
    
        try {
            //HEADERS
            //$this->authorization->buildAuthorizationHeader('tsec');
            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            $NimbleApi->authorization->addHeader('Accept', 'application/json');
            
            $NimbleApi->setUri('check');
            $NimbleApi->method = 'GET';
            $response = $NimbleApi->restApiCall();
            return $response;
        } catch (Exception $e) {
            throw new Exception('Error in NimbleAPICredentials::check(): ' . $e);
        }
    }
}