<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AkadType extends Model
{
    protected $table = 'akad_types';
    protected $fillable = ['kode_akad', 'nama_akad', 'kategori', 'ead_rule_type', 'requires_due_date', 'active'];
    protected $casts = ['requires_due_date' => 'boolean', 'active' => 'boolean'];

    public function productMappings(): HasMany
    {
        return $this->hasMany(ProductAkadMapping::class, 'akad_code', 'kode_akad');
    }
}
