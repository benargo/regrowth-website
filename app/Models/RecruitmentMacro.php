<?php

namespace App\Models;

use App\Enums\RecruitmentMacroType;
use App\Policies\RecruitmentMacroPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

#[Fillable(['title', 'type', 'content', 'created_by'])]
#[UsePolicy(RecruitmentMacroPolicy::class)]
class RecruitmentMacro extends Model implements Sortable
{
    use HasFactory, HasUuids, MassPrunable, SoftDeletes, SortableTrait;

    /**
     * The number of days a trashed macro is kept before it is permanently deleted.
     */
    public const int TRASH_DAYS = 30;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RecruitmentMacroType::class,
        ];
    }

    /**
     * Get the user who created the macro.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the query selecting macros that have been trashed for at least the trash period.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::TRASH_DAYS));
    }
}
