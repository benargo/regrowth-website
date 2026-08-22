<?php

namespace App\Models;

use App\Contracts\HasBlizzardIcons;
use App\Enums\DailyQuestType;
use App\Enums\Instance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['name', 'type', 'instance'])]
class DailyQuest extends Model implements HasBlizzardIcons, HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'daily_quests';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DailyQuestType::class,
            'instance' => Instance::class,
        ];
    }

    // ============ Custom attributes ============

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => in_array($this->type, [DailyQuestType::Dungeon, DailyQuestType::Heroic]) && $this->instance
                ? "{$this->name} ({$this->instance->value})"
                : $this->name,
        );
    }

    // ============ Laravel Media Library ============

    /**
     * Register media collections for the model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('blizzard_icons')->singleFile();
    }

    // ============ Relationships ============

    /**
     * The items rewarded for completing this quest.
     *
     * @return BelongsToMany<Item, $this>
     */
    public function rewards(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'pivot_dailyquest_rewards', 'daily_quest_id', 'item_id')
            ->withPivot('quantity');
    }
}
