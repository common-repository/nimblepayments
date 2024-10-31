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
 * Class NimbleAPIConfig
 * Placeholder for Nimble Config
 *
 * @package Base\Core
 */
class NimbleAPIConfig
{

    const SDK_NAME = 'Nimble-PHP-SDK';
    const SDK_VERSION = '1.2';

    /**
     *
     * @var string OAUTH_URL constant var, with the base url to connect with Oauth
     */
    const OAUTH_URL = "https://www.nimblepayments.com/auth/tsec/token";
    const OAUTH3_URL_AUTH = "https://www.nimblepayments.com/auth/tsec/authorize";
    const OTP_URL = "https://www.nimblepayments.com/auth/otp";

    /**
     *
     * @var string NIMBLE_API_BASE_URLs constant var, with the base url of live enviroment to make requests
     */
    const NIMBLE_API_BASE_URL = "https://www.nimblepayments.com/api/";

    /**
     *
     * @var string NIMBLE_API_BASE_URLs constant var, with the base url of demo enviroment to make requests
     */
    const NIMBLE_API_BASE_URL_DEMO = "https://www.nimblepayments.com/sandbox-api/";

    /**
     *
     * @var string GATEWAY_URL constant var
     */
    const GATEWAY_URL = "https://www.nimblepayments.com/private/partners/payment-gateway";
    
    /**
     *
     * @var int TIMEOUT (seconds) constant var
     */
    const TIMEOUT = 30;
    
    /**
     *
     * @var string MODE constant var
     */
    const MODE = 'real';
}
