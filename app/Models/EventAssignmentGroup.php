<?php

namespace App\Models;

use Database\Factories\EventAssignmentGroupFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EventAssignmentGroup extends Model
{
    /** @use HasFactory<EventAssignmentGroupFactory> */
    use HasFactory;

    /**
     * The model's default values.
     */
    protected $attributes = [
        'name' => 'New group',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'name',
        'notes',
        'sort_order',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'event_id',
        'created_at',
        'updated_at',
    ];

    /**
     * Format the notes attribute as markdown.
     *
     * @return Attribute<string|null>
     */
    protected function notes(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Str::markdown($value) : null,
        )->shouldCache();
    }

    /**
     * The event this group belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The assignments that belong to this group.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EventAssignment::class, 'group_id');
    }
}
