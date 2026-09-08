<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PantoneColour extends Model
{
    protected $fillable = ['code', 'hex', 'lab_l', 'lab_a', 'lab_b'];
}
