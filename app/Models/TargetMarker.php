<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TargetMarker extends Model
{
    /** @var string */
    protected $primaryKey = 'slug';

    /** @var bool */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['slug', 'name'];
}
