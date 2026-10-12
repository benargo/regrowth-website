<?php

namespace App\Models;

use App\Data\PlannedAbsenceReasonData;
use App\Observers\PlannedAbsenceObserver;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([PlannedAbsenceObserver::class])]
#[Fillable(['character_id', 'user_id', 'start_date', 'end_date', 'reason', 'notes', 'created_by'])]
class PlannedAbsence extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'reason' => PlannedAbsenceReasonData::class,
        ];
    }

    /**
     * Prepare a date for array / JSON serialization.
     */
    public function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to absences whose last day (the start date when
     * open-ended) is before today.
     */
    #[Scope]
    protected function ended(Builder $query): void
    {
        $query->whereRaw('COALESCE(end_date, start_date) < ?', [today()]);
    }

    /**
     * Scope a query to current and upcoming absences: those whose last day
     * (the start date when open-ended) is today or later.
     */
    #[Scope]
    protected function notEnded(Builder $query): void
    {
        $query->whereRaw('COALESCE(end_date, start_date) >= ?', [today()]);
    }
}
