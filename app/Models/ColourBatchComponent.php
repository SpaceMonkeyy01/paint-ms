<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColourBatchComponent extends Model
{
    protected $guarded = [];
    protected $casts = ['grams' => 'float', 'pct' => 'float'];

    public function batch(): BelongsTo { return $this->belongsTo(ColourBatch::class, 'colour_batch_id'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}
