<?php
require 'config/config.php';
require 'vendor/autoload.php';
use \Firebase\JWT\JWT;

// Create JWT

function get_jwt_token()
{
    $issuer = ISSUER;
    $issued_at = time();
    $not_before = $issued_at + NOT_BEFORE;
    $expiry = $issued_at + EXPIRY;

    $token = array(
        "iss" => $issuer,
        "iat" => $issued_at,
        "nbf" => $not_before,
        "exp" => $expiry
    );       

    $jwt = JWT::encode($token, SECRET_KEY);
    
    return $jwt;
}

// Verify JWT

function is_jwt_valid($token)
{
    try
    {
        $jwt = JWT::decode($token, SECRET_KEY, array('HS256'));

        $valid = 1;
        $message = "";
    }
    catch (\Exception $e) 
    {
        switch(true) 
        {
            case get_class($e) === "Firebase\JWT\BeforeValidException" :
                $message = "JWT is not activated yet.";
                $valid = 0;
                break;
            case get_class($e) === "Firebase\JWT\ExpiredException" :
                $message = "JWT has expired.";
                $valid = 0;
                break;
            default :
                $message = "There is some problem with the JWT attached in Request Header.";
                $valid = 0;
                break;
        }
    }

    return array("valid" => $valid, "body" => $message);
}

?>