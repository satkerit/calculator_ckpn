<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WriteOff extends Model
{
    protected $table = 'write_offs';
    protected $fillable = ['financing_id', 'writeoff_date', 'amount', 'recovery_rate', 'reason'];
    protected $casts = ['writeoff_date' => 'date'];

    public function recoveries(): HasMany
    {
        return $this->hasMany(Recovery::class);
    }
}
