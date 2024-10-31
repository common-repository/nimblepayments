<?php

/**
 * Nimble-API-PHP : API v1.2
 *
 * PHP version 5.4.2
 *
 * @link https://github.com/nimblepayments/sdk-php
 * @filesource
 */

/**
 * Implements the Auth header of the request to perform the identification correctly according to the type of
 * request
 */
class NimbleAPIAuth {
    
    /**
     * Implements authorization process on NimbleAPI object (basic or 3legged)
     * @param  object $NimbleApi NimbleAPI object to authorize
     * @return boolean           wether or not was authorized
     */
    public static function getBasicAuthorization($NimbleApi) {
        
        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty');
        }
        
        try {
            $NimbleApi->uri_oauth = true;
            //HEADERS
            //$this->authorization->buildAuthorizationHeader('basic');
            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            $NimbleApi->authorization->addHeader('Accept', 'application/json');

            
            $NimbleApi->setGetfields('?grant_type=client_credentials&scope=PAYMENT');
            $NimbleApi->method = 'POST';
            
            $response = $NimbleApi->restApiCall();
            
            $NimbleApi->setGetfields(null);
            
            if (isset($response['result']) && $response['result']['code'] != "200") {
                switch ($response['result']['code']) {
                    case '401':
                    default:
                        throw new Exception($response['result']['code'] . ' ' . $response['result']['info']);
                }
            } else {
                $NimbleApi->authorization->setAccessParams($response);
            }

            return true;
        } catch (Exception $e) {
            throw new Exception('Failed in getBasicAuthorization: ' . $e);
        }
    }

    /**
     * Implements authorization process on NimbleAPI object (basic or 3legged)
     * @param  object $NimbleApi NimbleAPI object to authorize
     * @return boolean           wether or not was authorized
     */
    public static function getCodeAuthorization($NimbleApi, $oauth_code) {
        
        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty');
        }
        if (empty($oauth_code)) {
            throw new Exception('$oauth_code parameter is empty');
        }
        
        try {
            $NimbleApi->uri_oauth = true;
            //HEADERS
            //$this->authorization->buildAuthorizationHeader('basic');
            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            $NimbleApi->authorization->addHeader('Accept', 'application/json');
            
            $NimbleApi->setGetfields('?grant_type=authorization_code&code=' . $oauth_code);
            $NimbleApi->method = 'POST';
            $response = $NimbleApi->restApiCall();

            $NimbleApi->setGetfields(null);

            if (isset($response['result']) && $response['result']['code'] != "200") {
                switch ($response['result']['code']) {
                    case '401':
                    default:
                        throw new Exception($response['result']['code'] . ' ' . $response['result']['info']);
                }
            } else {
                $NimbleApi->authorization->setAccessParams($response);
            }

            return true;
        } catch (Exception $e) {
            throw new Exception('Failed in getCodeAuthorization: ' . $e);
        }
    }

    /**
     * Refresh token callback implementation
     * @param  object $NimbleApi NimbleAPI object
     * @return boolean            wether the refresh operation was succesfully executed or not
     */
    public static function refreshToken($NimbleApi) {
        
        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty');
        }
        
        try {
            $NimbleApi->uri_oauth = true;
            //HEADERS
            //$this->authorization->buildAuthorizationHeader('basic');
            
            $NimbleApi->setGetfields('?grant_type=refresh_token');
            $postfields = array(
                'refresh_token' => $NimbleApi->authorization->getRefreshToken()
            );
            $NimbleApi->setPostfields(http_build_query($postfields));

            $NimbleApi->method = 'POST';
            $NimbleApi->authorization->setAccessToken(null);
            $response = $NimbleApi->restApiCall();

            $NimbleApi->setGetfields(null);

            if (isset($response['result']) && $response['result']['code'] != "200") {
                switch ($response['result']['code']) {
                    case '401':
                    default:
                        throw new Exception($response['result']['code'] . ' ' . $response['result']['info']);
                }
            } else {
                $NimbleApi->authorization->setAccessParams($response);
            }

            return true;
        } catch (Exception $e) {
            throw new Exception('Failed in refreshToken: ' . $e);
        }
    }

}
