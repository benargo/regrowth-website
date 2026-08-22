<?php

namespace App\Models;

use Database\Factories\TargetMarkerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name'])]
#[Table(key: 'slug', keyType: 'string', incrementing: false, timestamps: false)]
class TargetMarker extends Model
{
    /** @use HasFactory<TargetMarkerFactory> */
    use HasFactory;
}
