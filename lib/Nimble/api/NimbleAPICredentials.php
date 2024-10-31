<?php
require_once(dirname(__FILE__).'/../base/NimbleAPIConfig.php');
require_once 'NimbleAPIEnvironment.php';

class NimbleAPICredentials
{
    /**
     * DEPRECATED
     */
    public static function check($NimbleApi)
    {
       return NimbleAPIEnvironment::verification($NimbleApi);
    }
}