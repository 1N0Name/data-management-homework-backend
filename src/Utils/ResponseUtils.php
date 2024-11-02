<?php
namespace App\Utils;

use Psr\Http\Message\ResponseInterface as Response;
use Exception;

class ResponseUtils {

    public static function handleRequest(Response $response, callable $callback) {
        try {
            $result = $callback();
            return self::jsonResponse($response, $result);
        } catch (Exception $e) {
            return self::jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    public static function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, DELETE, PUT')
        ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Requested-With')
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
    }
}
