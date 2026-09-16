<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobType extends Model
{
    protected $fillable = [
        'label',
        'pattern',
        'cups_queue',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
