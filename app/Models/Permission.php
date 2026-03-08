<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'guard_name',
        'group',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'guard_name',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the group attribute, ensuring it's stored in snake_case.
     */
    protected function group(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? Str::slug($value) : null
        );
    }
}
