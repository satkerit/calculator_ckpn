<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recovery extends Model
{
    protected $table = 'recoveries';
    protected $fillable = ['write_off_id', 'recovery_date', 'amount', 'recovery_source'];
    protected $casts = ['recovery_date' => 'date'];

    public function writeOff(): BelongsTo
    {
        return $this->belongsTo(WriteOff::class);
    }
}
