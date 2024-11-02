<?php
namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utils\ResponseUtils;

class SaleController {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function getSalesHistory(Request $request, Response $response, $args) {
        return ResponseUtils::handleRequest($response, function () use ($args) {
            $stmt = $this->db->prepare("SELECT ps.ID, ps.SaleDate, ps.ProductCount, p.ID as ProductID, p.Title as Product
                                        FROM ProductSale ps
                                        JOIN Product p ON ps.ProductID = p.ID
                                        WHERE ps.AgentID = :agent_id ORDER BY ps.SaleDate DESC");
            $stmt->execute(['agent_id' => (int)$args['agent_id']]);
            return $stmt->fetchAll();
        });
    }

    public function addSale(Request $request, Response $response) {
        return ResponseUtils::handleRequest($response, function () use ($request) {
            $data = $request->getParsedBody();
            
            $saleDate = $data['sale_date'] ?? null;
            $query = "INSERT INTO ProductSale (AgentID, ProductID, ProductCount, SaleDate)
                      VALUES (:agent_id, :product_id, :product_count, " . ($saleDate ? ":sale_date" : "NOW()") . ")";
            
            $stmt = $this->db->prepare($query);
            
            $params = [
                'agent_id' => $data['agent_id'],
                'product_id' => $data['product_id'],
                'product_count' => $data['product_count']
            ];
    
            if ($saleDate) {
                $params['sale_date'] = $saleDate;
            }
    
            $stmt->execute($params);
            
            return ['success' => true];
        });
    }

    public function deleteSale(Request $request, Response $response, $args) {
        return ResponseUtils::handleRequest($response, function () use ($args) {
            $stmt = $this->db->prepare("DELETE FROM ProductSale WHERE ID = :sale_id");
            $stmt->execute(['sale_id' => (int)$args['id']]);
            return ['success' => true];
        });
    }
}
