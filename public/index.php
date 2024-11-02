<?php
require '../vendor/autoload.php';

use Slim\Factory\AppFactory;
use App\Controllers\AgentController;
use App\Controllers\ProductController;
use App\Controllers\SaleController;

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->setBasePath('/api');

$app->group('/agents', function ($group) {
    $group->get('', [AgentController::class, 'getAllAgents']);
    $group->get('/{id}', [AgentController::class, 'getAgentInfo']);
    $group->post('', [AgentController::class, 'addAgent']);
    $group->put('/{id}', [AgentController::class, 'updateAgent']);
    $group->delete('/{id}', [AgentController::class, 'deleteAgent']);
    $group->post('/priority', [AgentController::class, 'updatePriority']);
});

$app->get('/agent-types', [AgentController::class, 'getAgentTypes']);

$app->group('/agents/{agent_id}/sales', function ($group) {
    $group->get('', [SaleController::class, 'getSalesHistory']);
});

$app->group('/sales', function ($group) {
    $group->post('', [SaleController::class, 'addSale']);
    $group->delete('/{id}', [SaleController::class, 'deleteSale']);
});

$app->group('/products', function ($group) {
    $group->get('', [ProductController::class, 'getAllProducts']);
});

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$app->run();
