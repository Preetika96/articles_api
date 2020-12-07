<?php

require 'config/database.php';
require 'models/articles.php';
require 'utilities/authenticate.php';

// API Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Check Authentication

$is_authorized = is_authorized();

if (!$is_authorized || ($is_authorized && $is_authorized['valid'] == 0)) 
{
    header("HTTP/1.1 401 Unauthorized");
    echo $is_authorized['body'];

    exit(0);
}

// If Authenticated

$db = new Database();
$connection = $db->startConnection();

$requestMethod = $_SERVER["REQUEST_METHOD"];
$data = json_decode(file_get_contents("php://input"));
$parameters = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
$uri = explode("/", parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if(count($uri) <= 3)
{
    $articles = new Articles($connection, $requestMethod, $data, $parameters, $uri);
    $articles->processRequest();
}
else
{
    header("HTTP/1.1 400 Bad Request");
    echo "Extra URIs are passed.";
}

// Validate Auth Header and Token

function is_authorized() 
{
    switch(true) 
    {
        case array_key_exists('HTTP_AUTHORIZATION', $_SERVER) :
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            break;
        case array_key_exists('Authorization', $_SERVER) :
            $authHeader = $_SERVER['Authorization'];
            break;
        default :
            $authHeader = null;
            break;
    }

    preg_match('/Bearer\s(\S+)/', $authHeader, $token);

    if(!isset($token[1])) 
    {
        return false;
    }
    else
    {
        return is_jwt_valid($token[1]);
    }
}

?>