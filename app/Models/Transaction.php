<?php

namespace App\Models;

use App\Enums\IssueType;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $guarded = [];
    protected $casts = [
        'occurred_at' => 'datetime',
        'type' => TransactionType::class,
        'issue_type' => IssueType::class,
        'qty' => 'float',
        'rate' => 'float',
        'value' => 'float',
    ];

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function colourBatch(): BelongsTo { return $this->belongsTo(ColourBatch::class); }
    public function enteredBy(): BelongsTo { return $this->belongsTo(User::class, 'entered_by_id'); }
}
