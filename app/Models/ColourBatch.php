<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ColourBatch extends Model
{
    protected $guarded = [];
    protected $casts = ['mixed_at' => 'datetime', 'batch_grams' => 'float'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function components(): HasMany { return $this->hasMany(ColourBatchComponent::class); }
}
