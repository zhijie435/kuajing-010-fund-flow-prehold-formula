<?php

namespace App\Models;

class WithholdingDetail extends Model
{
    protected $table = 'withholding_details';
    protected $primaryKey = 'id';
    protected $fillable = [
        'formula_id', 'formula_code', 'formula_name', 'formula',
        'variables', 'result', 'order_no', 'related_type', 'related_id',
        'operator', 'remark', 'status'
    ];
    protected $timestamps = false;

    public function findByOrderNo(string $orderNo): array
    {
        return $this->where('order_no', '=', $orderNo);
    }

    public function findByFormulaId(int $formulaId): array
    {
        return $this->where('formula_id', '=', $formulaId);
    }

    public function getByRelated(string $relatedType, int $relatedId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE related_type = ? AND related_id = ? ORDER BY id DESC";
        return $this->db->fetchAll($sql, [$relatedType, $relatedId]);
    }
}
