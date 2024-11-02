<?php
namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utils\ResponseUtils;

class ProductController {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function getAllProducts(Request $request, Response $response) {
        return ResponseUtils::handleRequest($response, function () {
            $stmt = $this->db->prepare("SELECT ID, Title FROM Product");
            $stmt->execute();
            return $stmt->fetchAll();
        });
    }    
}
