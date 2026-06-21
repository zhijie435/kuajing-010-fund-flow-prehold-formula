<?php

namespace App\Models;

use App\Services\Database;

abstract class Model
{
    protected $table = '';
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $timestamps = true;
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function find(int $id)
    {
        $sql = sprintf("SELECT * FROM %s WHERE %s = ?", $this->table, $this->primaryKey);
        return $this->db->fetch($sql, [$id]);
    }

    public function all(): array
    {
        $sql = sprintf("SELECT * FROM %s ORDER BY %s DESC", $this->table, $this->primaryKey);
        return $this->db->fetchAll($sql);
    }

    public function where(string $column, string $operator, $value): array
    {
        $sql = sprintf("SELECT * FROM %s WHERE %s %s ? ORDER BY %s DESC", $this->table, $column, $operator, $this->primaryKey);
        return $this->db->fetchAll($sql, [$value]);
    }

    public function whereOne(string $column, string $operator, $value)
    {
        $sql = sprintf("SELECT * FROM %s WHERE %s %s ?", $this->table, $column, $operator);
        return $this->db->fetch($sql, [$value]);
    }

    public function create(array $data): int
    {
        $fillableData = $this->filterFillable($data);
        
        if ($this->timestamps && !isset($fillableData['updated_at'])) {
            $fillableData['updated_at'] = date('Y-m-d H:i:s');
        }
        
        return $this->db->insert($this->table, $fillableData);
    }

    public function update(int $id, array $data): int
    {
        $fillableData = $this->filterFillable($data);
        
        if ($this->timestamps) {
            $fillableData['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $where = sprintf("%s = :id", $this->primaryKey);
        $whereParams = ['id' => $id];
        
        return $this->db->update($this->table, $fillableData, $where, $whereParams);
    }

    public function delete(int $id): int
    {
        $where = sprintf("%s = ?", $this->primaryKey);
        return $this->db->delete($this->table, $where, [$id]);
    }

    public function paginate(int $page = 1, int $perPage = 20, array $conditions = []): array
    {
        $offset = ($page - 1) * $perPage;
        $whereClause = '';
        $params = [];
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                if (is_array($value)) {
                    $whereParts[] = "$column {$value[0]} ?";
                    $params[] = $value[1];
                } else {
                    $whereParts[] = "$column = ?";
                    $params[] = $value;
                }
            }
            $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
        }
        
        $sql = sprintf("SELECT * FROM %s %s ORDER BY %s DESC LIMIT %d OFFSET %d", 
            $this->table, $whereClause, $this->primaryKey, $perPage, $offset);
        
        $countSql = sprintf("SELECT COUNT(*) as total FROM %s %s", $this->table, $whereClause);
        $total = $this->db->fetch($countSql, $params)['total'];
        
        return [
            'data' => $this->db->fetchAll($sql, $params),
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }

    public function getDb()
    {
        return $this->db;
    }
}
