<?php

require 'utilities/authenticate.php';

// API Headers

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$data = json_decode(file_get_contents("php://input"));

// Get JWT

if($data->static_key && strcmp($data->static_key, STATIC_KEY) == 0)
{
    $jwt_output = get_jwt_token();

    http_response_code(200);
    echo json_encode(array("jwt" => $jwt_output));
}
else
{
    header("HTTP/1.1 401 Unauthorized");
    exit('Unauthorized');
}

?>