<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StationSlot extends Model
{
    protected $fillable = ['slot', 'class', 'item_id', 'position', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
