<?php

namespace App\Controllers;

use App\Services\Router;
use App\Services\WithholdingCalculator;
use App\Models\WithholdingFormula;
use App\Models\WithholdingDetail;
use App\Models\FundFlow;

class DashboardController
{
    private $calculator;
    private $formulaModel;
    private $detailModel;
    private $fundFlowModel;
    private $router;

    public function __construct()
    {
        $this->calculator = new WithholdingCalculator();
        $this->formulaModel = new WithholdingFormula();
        $this->detailModel = new WithholdingDetail();
        $this->fundFlowModel = new FundFlow();
        $this->router = new Router();
    }

    public function index()
    {
        $db = $this->formulaModel->getDb();
        
        $formulaCount = $db->fetch("SELECT COUNT(*) as count FROM withholding_formulas WHERE status = 1")['count'];
        $detailCount = $db->fetch("SELECT COUNT(*) as count FROM withholding_details")['count'];
        $flowCount = $db->fetch("SELECT COUNT(*) as count FROM fund_flows")['count'];
        $currentBalance = $this->fundFlowModel->getLatestBalance();
        
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        
        $todayWithholding = $db->fetch(
            "SELECT COALESCE(SUM(result), 0) as total FROM withholding_details WHERE created_at >= ? AND created_at <= ?",
            [$todayStart, $todayEnd]
        )['total'];
        
        $todayFundIn = $db->fetch(
            "SELECT COALESCE(SUM(amount), 0) as total FROM fund_flows WHERE direction = 1 AND created_at >= ? AND created_at <= ?",
            [$todayStart, $todayEnd]
        )['total'];
        
        $todayFundOut = $db->fetch(
            "SELECT COALESCE(SUM(amount), 0) as total FROM fund_flows WHERE direction = 2 AND created_at >= ? AND created_at <= ?",
            [$todayStart, $todayEnd]
        )['total'];
        
        $recentDetails = $db->fetchAll(
            "SELECT * FROM withholding_details ORDER BY id DESC LIMIT 5"
        );
        
        foreach ($recentDetails as &$item) {
            if (!empty($item['variables'])) {
                $item['variables'] = json_decode($item['variables'], true);
            }
        }
        
        $recentFlows = $db->fetchAll(
            "SELECT * FROM fund_flows ORDER BY id DESC LIMIT 5"
        );
        
        $formulaStats = $db->fetchAll(
            "SELECT f.id, f.name, f.code, COUNT(d.id) as usage_count, COALESCE(SUM(d.result), 0) as total_amount
             FROM withholding_formulas f
             LEFT JOIN withholding_details d ON f.id = d.formula_id
             GROUP BY f.id, f.name, f.code
             ORDER BY usage_count DESC
             LIMIT 5"
        );
        
        return $this->router->success([
            'cards' => [
                'formula_count' => (int)$formulaCount,
                'detail_count' => (int)$detailCount,
                'flow_count' => (int)$flowCount,
                'current_balance' => round((float)$currentBalance, 2)
            ],
            'today' => [
                'withholding_amount' => round((float)$todayWithholding, 2),
                'fund_in' => round((float)$todayFundIn, 2),
                'fund_out' => round((float)$todayFundOut, 2)
            ],
            'recent_details' => $recentDetails,
            'recent_flows' => $recentFlows,
            'formula_stats' => $formulaStats
        ]);
    }
}
