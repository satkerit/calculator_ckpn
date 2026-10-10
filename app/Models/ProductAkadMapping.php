<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAkadMapping extends Model
{
    protected $table = 'product_akad_mappings';
    protected $fillable = ['product_code', 'product_name', 'akad_code', 'akad_name', 'ead_rule_type', 'active', 'description'];
    protected $casts = ['active' => 'boolean'];

    public function akadType()
    {
        return $this->belongsTo(AkadType::class, 'akad_code', 'kode_akad');
    }
}
