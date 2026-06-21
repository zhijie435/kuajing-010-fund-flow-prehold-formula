<?php

namespace App\Models;

class WithholdingFormula extends Model
{
    protected $table = 'withholding_formulas';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'code', 'formula', 'description', 'variables', 'status'];
    protected $timestamps = true;

    public function findByCode(string $code)
    {
        return $this->whereOne('code', '=', $code);
    }

    public function allActive(): array
    {
        return $this->where('status', '=', 1);
    }
}
