<?php

/**
 * Nimble-API-PHP : API v1.2
 *
 * PHP version 5.4.2
 *
 * @link https://github.com/nimblepayments/sdk-php
 * @filesource
 */
require_once(dirname(__FILE__) . '/../base/NimbleAPIConfig.php');

/**
 * Class responsible for performing payments services.
 */
class NimbleAPIPayments {

    /**
     * Method sendPaymentClient
     *
     * @param object $NimbleApi
     * @param array $context
     * @return array
     */
    public static function sendPaymentClient($NimbleApi, $context) {

        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty.');
        }
        if (empty($context)) {
            throw new Exception('$payment parameter is empty, please enter a payment');
        }

        try {
            //HEADERS
            //$this->authorization->buildAuthorizationHeader('tsec');
            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            $NimbleApi->authorization->addHeader('Accept', 'application/json');
            $NimbleApi->authorization->addLanguageHeader();

            $NimbleApi->setPostfields(json_encode($context));
            $NimbleApi->uri = 'v2/payments';
            $NimbleApi->method = 'POST';
            $response = $NimbleApi->restApiCall();
            return $response;
        } catch (Exception $e) {
            throw new Exception('Error in sendPaymentClient: ' . $e);
        }
    }

    /**
     * Method updateCustomerData (DEPRECATED)
     *
     * @param object $NimbleApi
     * @param array $transaction_id
     * @param array $new_order_id
     * @return array
     */
    public static function updateCustomerData($NimbleApi, $transaction_id, $new_order_id) {
        self::updateMerchantOrderId($NimbleApi, $transaction_id, $new_order_id);
    }
    
    /**
     * Method updateMerchantOrderId
     *
     * @param object $NimbleApi
     * @param array $transaction_id
     * @param array $new_order_id
     * @return array
     */
    public static function updateMerchantOrderId($NimbleApi, $transaction_id, $new_order_id) {

        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty.');
        }
        if (empty($transaction_id)) {
            throw new Exception('$payment parameter is empty, please enter a payment');
        }
        if (empty($new_order_id)) {
            throw new Exception('$new_order_id parameter is empty, please enter a new_order_id');
        }

        try {
            //HEADERS
            //$this->authorization->buildAuthorizationHeader('tsec');
            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            $NimbleApi->authorization->addHeader('Accept', 'application/json');
            
            $aux_order = array('merchantOrderId' => $new_order_id);
            $NimbleApi->setPostfields(json_encode($aux_order));
            $NimbleApi->uri = 'v2/payments/' . $transaction_id;
            $NimbleApi->method = 'PUT';
            $response = $NimbleApi->restApiCall();
            return $response;
        } catch (Exception $e) {
            throw new Exception('Error in updateCustomerData: ' . $e);
        }
    }

    /**
     * Method sendPaymentRefund
     */
    public static function sendPaymentRefund($NimbleApi, $IdTransaction, $refund)
    {
    
        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty in sendPaymentRefund.');
        }
        if (empty($refund)) {
            throw new Exception('$refund parameter is empty, please enter a refund in sendPaymentRefund');
        }
        if (empty($IdTransaction)) {
            throw new Exception('$IdTransaction parameter is empty, please enter a IdTransaction');
        }
    
        try {

            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            $NimbleApi->authorization->addHeader('Accept', 'application/json');
            
            $NimbleApi->setPostfields(json_encode($refund));
            $NimbleApi->setURI('v2/payments/'.$IdTransaction.'/refunds');
            $NimbleApi->method = 'POST';
            $response = $NimbleApi->restApiCall();
            if (!is_null($response)) {
                if (isset($response["data"])) {
                    return $response;
                } else {
                    if (isset($response["result"]["internal_code"])) {
                        return array("error" => "Error: ". $response["result"]["internal_code"] ." on sendPaymentRefund");
                    } else {
                        return array("error" => "Error: ". $response["result"]["code"] . " ". $response["result"]["info"] ." on sendPaymentRefund");
                    }
                }
            } else {
                return array("error" => "Error: The call to the API Nimble didn't respond on sendPaymentRefund");
            }
        } catch (Exception $e) {
            throw new Exception('Error in sendPaymentRefund: ' . $e);
        }
    }
    
    /**
     * Method getPaymentRefunds
     */
    public static function getPaymentRefunds($NimbleApi, $IdTransaction)
    {
    
        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty in getPaymentRefunds.');
        }
        if (empty($IdTransaction)) {
            throw new Exception('$IdTransaction parameter is empty, please enter a IdTransaction');
        }
    
        try {
            //HEADERS
            //$this->authorization->buildAuthorizationHeader('tsec');
            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            $NimbleApi->authorization->addHeader('Accept', 'application/json');
            
            $NimbleApi->setURI('v2/payments/'.$IdTransaction.'/refunds');
            $NimbleApi->method = 'GET';
            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            $response = $NimbleApi->restApiCall();
            return $response;
        } catch (Exception $e) {
            throw new Exception('Error in getPaymentRefunds: ' . $e);
        }
    }
    
     /**
     * Method getPaymentStatus
     *
     * @param object $NimbleApi
     * @param $IdTransaction 
     * @param array $merchantOrderId
     * @return array
     */
    public static function getPaymentStatus($NimbleApi, $IdTransaction = null, $merchantOrderId = null)
    {
        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty.');
        }
    
        try {
            if ($IdTransaction) {
                $NimbleApi->uri = 'v2/payments/status/' . $IdTransaction;
            } else if ($merchantOrderId) {
                $NimbleApi->uri = 'v2/payments/status?' . http_build_query(array('merchantOrderId' => $merchantOrderId));
            } else {
                $NimbleApi->uri = 'v2/payments/status';
            }

            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            $NimbleApi->method = 'GET';
            $response = $NimbleApi->restApiCall();
            $NimbleApi->setGetfields(null);
            
            return $response;
        } catch (Exception $e) {
            throw new Exception('Error in NimbleAPIPayments::getPaymentStatus: ' . $e);
        }       
    }
 
    /*
     * Get Payments Summary: Sales summary [last 7 days, last month]
     */
    public static function getSummary($NimbleApi, $filters){
        //TODO
    }
    
    /**
     * Method getPayment
     *
     * @param object $NimbleApi
     * @param $IdTransaction
     * @return array
     */
    public static function getPayment($NimbleApi, $IdTransaction, $extendedData = false) {

        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty.');
        }
        if (empty($IdTransaction)) {
            throw new Exception('$payment parameter is empty, please enter a payment');
        }

        try {
            //HEADERS
            //$this->authorization->buildAuthorizationHeader('tsec');
            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            $NimbleApi->authorization->addHeader('Accept', 'application/json');
            
            $query_params = ($extendedData) ? '?extendedData=true' : '';
            $NimbleApi->uri = 'v2/payments/' . $IdTransaction . $query_params;
            $NimbleApi->method = 'GET';
            $response = $NimbleApi->restApiCall();
            return $response;
        } catch (Exception $e) {
            throw new Exception('Error in getPayment: ' . $e);
        }
    }
    
    /**
     * Method getPayments
     *
     * @param object $NimbleApi
     * @param array $filters (fromDate, toDate, merchantOrderId, status, hasRefunds, extendedData, hasDisputes,
     *                        entryReference, itemReference, itemsPerPage)
     * @return array
     */
    public static function getPayments($NimbleApi, $filters = array()) {

        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty.');
        }
        
        try {
            if (!empty($filters)) {
                $uri_filters = http_build_query($filters);
                $NimbleApi->setURI('v2/payments?' . $uri_filters);
            }else{
                $NimbleApi->setURI('v2/payments/');
            }
            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            $NimbleApi->method = 'GET';
            $response = $NimbleApi->restApiCall();
            return $response;
        } catch (Exception $e) {
            throw new Exception('Error in getPayments: ' . $e);
        }
    }
    
    /**
     * Method getPaymentDisputes
     *
     * @param object $NimbleApi
     * @param $IdTransaction 
     * @return array
     */
    public static function getPaymentDisputes($NimbleApi, $IdTransaction) {

        if (empty($NimbleApi)) {
            throw new Exception('$NimbleApi parameter is empty.');
        }
        
         if (empty($IdTransaction)) {
            throw new Exception('$IdTransaction parameter is empty, please enter a payment');
        }

        try {
            $NimbleApi->authorization->addHeader('Content-Type', 'application/json');
            $NimbleApi->uri = 'v2/payments/' . $IdTransaction . '/disputes';
            $NimbleApi->method = 'GET';
            $response = $NimbleApi->restApiCall();
            return $response;
        } catch (Exception $e) {
            throw new Exception('Error in getPaymentsDisputes: ' . $e);
        }
    }
}
