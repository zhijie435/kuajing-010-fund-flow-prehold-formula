<?php

require_once __DIR__ . '/autoload.php';

use App\Services\Router;
use App\Controllers\DashboardController;
use App\Controllers\WithholdingFormulaController;
use App\Controllers\WithholdingController;
use App\Controllers\FundFlowController;

$config = require __DIR__ . '/config/config.php';
date_default_timezone_set($config['app']['timezone']);

$router = new Router();

$router->options('.*', function() use ($router) {
    $router->sendCorsHeaders();
    return $router->jsonResponse(['message' => 'OK']);
});

$router->get('/api/dashboard', [DashboardController::class, 'index']);

$router->get('/api/withholding-formulas', [WithholdingFormulaController::class, 'index']);
$router->get('/api/withholding-formulas/active', [WithholdingFormulaController::class, 'allActive']);
$router->get('/api/withholding-formulas/{id}', [WithholdingFormulaController::class, 'show']);
$router->post('/api/withholding-formulas', [WithholdingFormulaController::class, 'store']);
$router->put('/api/withholding-formulas/{id}', [WithholdingFormulaController::class, 'update']);
$router->delete('/api/withholding-formulas/{id}', [WithholdingFormulaController::class, 'destroy']);
$router->post('/api/withholding-formulas/validate', [WithholdingFormulaController::class, 'validate']);

$router->post('/api/withholding/calculate', [WithholdingController::class, 'calculate']);
$router->post('/api/withholding/preview', [WithholdingController::class, 'preview']);
$router->post('/api/withholding/batch-calculate', [WithholdingController::class, 'batchCalculate']);
$router->get('/api/withholding/details', [WithholdingController::class, 'details']);
$router->get('/api/withholding/details/{id}', [WithholdingController::class, 'detail']);

$router->get('/api/fund-flows', [FundFlowController::class, 'index']);
$router->get('/api/fund-flows/types', [FundFlowController::class, 'types']);
$router->get('/api/fund-flows/stats', [FundFlowController::class, 'stats']);
$router->get('/api/fund-flows/{id}', [FundFlowController::class, 'show']);
$router->post('/api/fund-flows', [FundFlowController::class, 'store']);

$router->dispatch();
