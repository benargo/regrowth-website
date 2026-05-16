<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrunedModel extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['id', 'type', 'pruned_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'pruned_at' => 'datetime',
    ];
}
