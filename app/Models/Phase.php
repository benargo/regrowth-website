<?php

namespace App\Models;

use App\Contracts\Models\DatasetModel;
use App\Contracts\Models\EditLockable;
use App\Contracts\Models\HasUsage;
use App\Events\AddonSettingsProcessed;
use App\Helpers\Database\Eloquent\Traits\HasManyKeyBy;
use App\Models\Concerns\HasEditLock;
use App\Models\Concerns\TracksUsage;
use App\Observers\PhaseObserver;
use App\Policies\DatasetPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

#[ObservedBy([PhaseObserver::class])]
#[Fillable(['number', 'description', 'start_date', 'sort_order', 'game_version_id'])]
#[Hidden(['created_at', 'updated_at'])]
#[UsePolicy(DatasetPolicy::class)]
class Phase extends Model implements DatasetModel, EditLockable, HasUsage, Sortable
{
    use HasEditLock;
    use HasFactory;
    use HasManyKeyBy;
    use SortableTrait;
    use TracksUsage;

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'updated' => AddonSettingsProcessed::class,
    ];

    /**
     * Relationships whose existing rows mark this phase as in use.
     *
     * @return list<string>
     */
    public function usageRelations(): array
    {
        return ['raids', 'reports'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
        ];
    }

    // ============ Sorting ============

    /**
     * The phases sort_order ranks against: the unscheduled phases of the same
     * game version, or of no game version.
     */
    public function buildSortQuery(): Builder
    {
        return static::query()
            ->where('game_version_id', $this->game_version_id)
            ->whereNull('start_date');
    }

    /**
     * Order phases as they arrive: phases with a start date by that date,
     * then unscheduled phases in their sort order.
     */
    #[Scope]
    protected function inReleaseOrder(Builder $query): void
    {
        $query->orderByRaw('start_date IS NULL')
            ->orderBy('start_date')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    // ============ Custom attributes ============

    /**
     * Determine if the phase has started.
     */
    public function hasStarted(): bool
    {
        return $this->start_date?->isPast() ?? false;
    }

    /**
     * Scope a query to phases with a start date at or before now.
     */
    #[Scope]
    protected function started(Builder $query): void
    {
        $query->whereNotNull('start_date')->where('start_date', '<=', now());
    }

    /**
     * Get the phase number attribute.
     */
    protected function number(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return fmod($value, 1) === 0.0 ? (int) $value : $value;
            },
        );
    }

    /**
     * Get the phase's display name, e.g. "Phase 3.5".
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => "Phase {$this->number}",
        );
    }

    // ============ Relations ============

    /**
     * Get the game version that this phase belongs to.
     */
    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    /**
     * Get the raids that belong to this phase.
     */
    public function raids(): HasMany
    {
        return $this->hasManyKeyBy('id', Raid::class);
    }

    /**
     * Get the reports whose start time falls in this phase.
     *
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
