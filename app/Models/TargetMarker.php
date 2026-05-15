<?php

namespace App\Models;

use Database\Factories\TargetMarkerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TargetMarker extends Model
{
    /** @use HasFactory<TargetMarkerFactory> */
    use HasFactory;

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
