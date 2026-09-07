<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BomCategory extends Model
{
    protected $guarded = [];
    protected $casts = ['clubbed' => 'bool', 'ignored' => 'bool'];
}
