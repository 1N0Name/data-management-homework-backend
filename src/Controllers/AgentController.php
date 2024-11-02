<?php
namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utils\ResponseUtils;
use App\Utils\FileUtils;

class AgentController {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function getAllAgents(Request $request, Response $response) {
        return ResponseUtils::handleRequest($response, function () {
            $stmt = $this->db->query("SELECT a.*, 
                                      at.Title as AgentType,
                                      COALESCE((SELECT SUM(ps.ProductCount)
                                            FROM ProductSale ps
                                            WHERE ps.AgentID = a.ID), 0) as SalesCount,
                                      COALESCE((SELECT SUM(ps.ProductCount * p.MinCostForAgent) 
                                                FROM ProductSale ps
                                                LEFT JOIN Product p ON ps.ProductID = p.ID
                                                WHERE ps.AgentID = a.ID), 0) as TotalSales
                                      FROM Agent a
                                      LEFT JOIN AgentType at ON a.AgentTypeID = at.ID");
            $agents = $stmt->fetchAll();
    
            return array_map(function ($agent) {
                $agent['Discount'] = $this->calculateDiscount($agent['TotalSales']);
                return $agent;
            }, $agents);
        });
    }

    public function getAgentTypes(Request $request, Response $response) {
        return ResponseUtils::handleRequest($response, function () {
            return $this->db->query("SELECT ID, Title FROM AgentType")->fetchAll();
        });
    }

    public function getAgentInfo(Request $request, Response $response, $args) {
        return ResponseUtils::handleRequest($response, function () use ($args) {
            $stmt = $this->db->prepare("SELECT a.*,
                                       COALESCE(SUM(ps.ProductCount), 0) as SalesCount,
                                       COALESCE(SUM(ps.ProductCount * p.MinCostForAgent), 0) as TotalSales
                                       FROM Agent a
                                       LEFT JOIN AgentType at ON a.AgentTypeID = at.ID
                                       LEFT JOIN ProductSale ps ON a.ID = ps.AgentID
                                       LEFT JOIN Product p ON ps.ProductID = p.ID
                                       WHERE a.ID = :id
                                       GROUP BY a.ID, a.Title, a.Phone, at.Title");
            $stmt->execute(['id' => (int)$args['id']]);
            $agent = $stmt->fetch();
    
            if (!$agent) {
                throw new \Exception('Agent not found', 404);
            }
    
            $agent['Discount'] = $this->calculateDiscount($agent['TotalSales']);
            return $agent;
        });
    }

    public function updatePriority(Request $request, Response $response) {
        return ResponseUtils::handleRequest($response, function () use ($request) {
            $data = $request->getParsedBody();
            $inQuery = implode(',', array_fill(0, count($data['agentIds']), '?'));
            $stmt = $this->db->prepare("UPDATE Agent SET Priority = ? WHERE ID IN ($inQuery)");
            $stmt->execute(array_merge([$data['newPriority']], $data['agentIds']));
            return ['success' => true];
        });
    }

    public function updateAgent(Request $request, Response $response, $args) {
        return ResponseUtils::handleRequest($response, function () use ($request, $args) {
            $id = (int)$args['id'];
            
            $data = $request->getParsedBody();
            
            $fields = array_filter([
                'Title' => $data['name'] ?? null,
                'AgentTypeID' => $data['agent_type'] ?? null,
                'Address' => $data['address'] ?? null,
                'INN' => $data['inn'] ?? null,
                'KPP' => $data['kpp'] ?? null,
                'DirectorName' => $data['director_name'] ?? null,
                'Phone' => $data['phone'] ?? null,
                'Email' => $data['email'] ?? null,
                'Logo' => $data['logo'] ?? null,
                'Priority' => $data['priority'] ?? null
            ]);
    
            if (empty($fields)) {
                throw new \Exception('No fields to update', 400);
            }
            
            $setClause = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($fields)));
            $stmt = $this->db->prepare("UPDATE Agent SET $setClause WHERE ID = :ID");
            $fields['ID'] = $id;
            $stmt->execute($fields);
            
            return ['success' => true];
        });
    }              

    public function addAgent(Request $request, Response $response) {
        return ResponseUtils::handleRequest($response, function () use ($request) {
            $data = $request->getParsedBody();
    
            // Обязательные поля
            $requiredFields = ['name' => 'Title', 'agent_type' => 'AgentTypeID', 'phone' => 'Phone', 'priority' => 'Priority', 'inn' => 'INN'];
            $missingFields = array_filter($requiredFields, fn($key) => empty($data[$key]), ARRAY_FILTER_USE_KEY);
            
            if ($missingFields) {
                throw new \Exception("Missing required fields: " . implode(', ', array_keys($missingFields)), 400);
            }

            $optionalFields = ['address' => 'Address', 'kpp' => 'KPP', 'director_name' => 'DirectorName', 'email' => 'Email', 'logo' => 'Logo'];
    
            $fieldsMap = array_merge($requiredFields, $optionalFields);
            $filteredFields = array_filter($data, fn($value) => !empty($value));
    
            $columns = array_map(fn($key) => $fieldsMap[$key], array_keys($filteredFields));
            $placeholders = array_map(fn($key) => ":$key", array_keys($filteredFields));
    

            $sql = "INSERT INTO Agent (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->db->prepare($sql);
    
            $params = [];
            foreach ($filteredFields as $key => $value) {
                $params[":$key"] = $value;
            }
            $stmt->execute($params);

            return [
                'success' => true,
                'agent_id' => $this->db->lastInsertId()
            ];
        });
    }        

    public function deleteAgent(Request $request, Response $response, $args) {
        return ResponseUtils::handleRequest($response, function () use ($args) {
            $stmt = $this->db->prepare("SELECT COUNT(*) as SaleCount FROM ProductSale WHERE AgentID = :id");
            $stmt->execute(['id' => (int)$args['id']]);
            if ($stmt->fetchColumn() > 0) {
                throw new \Exception('Agent cannot be deleted due to sales history', 403);
            }

            $stmt = $this->db->prepare("DELETE FROM Agent WHERE ID = :id");
            $stmt->execute(['id' => (int)$args['id']]);

            return ['success' => true];
        });
    }

    // Функция расчета скидки
    private function calculateDiscount($totalSales) {
        return match (true) {
            $totalSales >= 500000 => 25,
            $totalSales >= 150000 => 20,
            $totalSales >= 50000  => 10,
            $totalSales >= 10000  => 5,
            default => 0
        };
    }
}
