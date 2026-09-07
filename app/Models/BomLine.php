<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomLine extends Model
{
    protected $guarded = [];
    protected $casts = ['allocated_qty' => 'float'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
