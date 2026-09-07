<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = [];
    protected $casts = [
        'total_area' => 'decimal:2',
        'surface_area' => 'decimal:2',
        'bom_total_cost' => 'decimal:2',
        'bom_loaded_at' => 'datetime',
        'bom_changed_after_issue_at' => 'datetime',
        'order_date' => 'date',
        'due_date' => 'date',
        'airtable_tags' => 'array',
    ];

    public function bomLines(): HasMany
    {
        return $this->hasMany(BomLine::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function colourBatches(): HasMany
    {
        return $this->hasMany(ColourBatch::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(AirtableSyncLog::class);
    }
}
