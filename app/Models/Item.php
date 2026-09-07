<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'bool', 'density_kg_per_l' => 'float', 'rate_per_uom' => 'float', 'min_level' => 'float'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Stock on hand = SUM(qty). Use StockService for bulk queries. */
    public function stockOnHand(): float
    {
        return (float) $this->transactions()->sum('qty');
    }

    public function gramsToLitres(float $grams): ?float
    {
        return $this->density_kg_per_l ? $grams / 1000 / $this->density_kg_per_l : null;
    }
}
