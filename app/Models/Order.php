<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = [];
    protected $casts = ['bom_loaded_at' => 'datetime'];

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
}
