<?php

/**
 * Nimble-API-PHP : API v1.2
 *
 * PHP version 5.2
 *
 * @link https://github.com/nimblepayments/sdk-php
 * @filesource
 */
class NimbleAPIAccount {
    //put your code here
    
    /*
     * Get Summary from merchant account. Total Available
     */
    public static function balanceSummary($NimbleApi){
       
        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty.');
        }
    
        try {
            $NimbleApi->uri ='v2/balance/summary' ;
            $NimbleApi->method = 'GET';
            $response = $NimbleApi->restApiCall();
            return $response;
        } catch (Exception $e) {
            throw new Exception('Error in NimbleAPIAccount::balanceSummary: ' . $e);
        }
    }
    
    /*
     * CashOut
     */
    public static function cashOut($NimbleApi, $transfer){
        
        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty.');
        }
        
        if (empty($transfer)) {
            throw new Exception('$transfer parameter is empty, please enter a $transfer');
        }
    
        try {
            //HEADERS
            //$this->authorization->buildAuthorizationHeader('tsec');
            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            
            $NimbleApi->setPostfields(json_encode($transfer));
            $NimbleApi->uri ='v2/cashout' ;
            $NimbleApi->method = 'POST';
            $response = $NimbleApi->restApiCall();
            return $response;
        } catch (Exception $e) {
            throw new Exception('Error in NimbleAPIAccount::cashOut: ' . $e);
        }
    }
}
