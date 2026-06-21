<?php

namespace App\Controllers;

use App\Services\Router;
use App\Models\WithholdingFormula;
use App\Services\WithholdingCalculator;

class WithholdingFormulaController
{
    private $model;
    private $calculator;
    private $router;

    public function __construct()
    {
        $this->model = new WithholdingFormula();
        $this->calculator = new WithholdingCalculator();
        $this->router = new Router();
    }

    public function index()
    {
        $input = $this->router->getInput();
        $page = (int)($input['page'] ?? 1);
        $perPage = (int)($input['per_page'] ?? 20);
        $status = $input['status'] ?? null;
        $keyword = $input['keyword'] ?? null;
        
        $conditions = [];
        if ($status !== null) {
            $conditions['status'] = (int)$status;
        }
        
        $result = $this->model->paginate($page, $perPage, $conditions);
        
        if ($keyword) {
            $db = $this->model->getDb();
            $searchSql = "SELECT * FROM withholding_formulas WHERE name LIKE ? OR code LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?";
            $countSql = "SELECT COUNT(*) as total FROM withholding_formulas WHERE name LIKE ? OR code LIKE ?";
            $searchKeyword = "%$keyword%";
            $offset = ($page - 1) * $perPage;
            
            $result['data'] = $db->fetchAll($searchSql, [$searchKeyword, $searchKeyword, $perPage, $offset]);
            $total = $db->fetch($countSql, [$searchKeyword, $searchKeyword]);
            $result['total'] = (int)$total['total'];
            $result['total_pages'] = (int)ceil($result['total'] / $perPage);
        }
        
        foreach ($result['data'] as &$item) {
            if (!empty($item['variables'])) {
                $item['variables'] = json_decode($item['variables'], true);
            }
        }
        
        return $this->router->success($result);
    }

    public function show($params)
    {
        $id = (int)$params['id'];
        $formula = $this->model->find($id);
        
        if (!$formula) {
            return $this->router->error('公式不存在', 404, 404);
        }
        
        if (!empty($formula['variables'])) {
            $formula['variables'] = json_decode($formula['variables'], true);
        }
        
        return $this->router->success($formula);
    }

    public function store()
    {
        $input = $this->router->getInput();
        
        if (empty($input['name'])) {
            return $this->router->error('公式名称不能为空');
        }
        if (empty($input['code'])) {
            return $this->router->error('公式编码不能为空');
        }
        if (empty($input['formula'])) {
            return $this->router->error('公式表达式不能为空');
        }
        
        $existing = $this->model->findByCode($input['code']);
        if ($existing) {
            return $this->router->error('公式编码已存在');
        }
        
        $variables = $input['variables'] ?? [];
        if (!is_array($variables)) {
            $variables = json_decode($variables, true) ?: [];
        }
        
        $validation = $this->calculator->validateFormula($input['formula'], $variables);
        if (!$validation['valid']) {
            return $this->router->error('公式验证失败: ' . implode(', ', $validation['errors']));
        }
        
        $data = [
            'name' => $input['name'],
            'code' => $input['code'],
            'formula' => $input['formula'],
            'description' => $input['description'] ?? '',
            'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE),
            'status' => (int)($input['status'] ?? 1)
        ];
        
        $id = $this->model->create($data);
        
        return $this->router->success(['id' => $id], '创建成功');
    }

    public function update($params)
    {
        $id = (int)$params['id'];
        $input = $this->router->getInput();
        
        $formula = $this->model->find($id);
        if (!$formula) {
            return $this->router->error('公式不存在', 404, 404);
        }
        
        if (empty($input['name'])) {
            return $this->router->error('公式名称不能为空');
        }
        if (empty($input['formula'])) {
            return $this->router->error('公式表达式不能为空');
        }
        
        $variables = $input['variables'] ?? [];
        if (is_string($variables)) {
            $variables = json_decode($variables, true) ?: [];
        }
        
        $validation = $this->calculator->validateFormula($input['formula'], $variables);
        if (!$validation['valid']) {
            return $this->router->error('公式验证失败: ' . implode(', ', $validation['errors']));
        }
        
        $data = [
            'name' => $input['name'],
            'formula' => $input['formula'],
            'description' => $input['description'] ?? '',
            'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE),
            'status' => (int)($input['status'] ?? 1)
        ];
        
        $this->model->update($id, $data);
        
        return $this->router->success(null, '更新成功');
    }

    public function destroy($params)
    {
        $id = (int)$params['id'];
        $formula = $this->model->find($id);
        
        if (!$formula) {
            return $this->router->error('公式不存在', 404, 404);
        }
        
        $this->model->delete($id);
        
        return $this->router->success(null, '删除成功');
    }

    public function validate()
    {
        $input = $this->router->getInput();
        
        $formula = $input['formula'] ?? '';
        $variables = $input['variables'] ?? [];
        
        if (is_string($variables)) {
            $variables = json_decode($variables, true) ?: [];
        }
        
        $result = $this->calculator->validateFormula($formula, $variables);
        
        return $this->router->success($result);
    }

    public function allActive()
    {
        $formulas = $this->model->allActive();
        
        foreach ($formulas as &$item) {
            if (!empty($item['variables'])) {
                $item['variables'] = json_decode($item['variables'], true);
            }
        }
        
        return $this->router->success($formulas);
    }
}
