<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirtableSyncLog extends Model
{
    public $timestamps = false;

    protected $table = 'airtable_sync_log';

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'synced_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
