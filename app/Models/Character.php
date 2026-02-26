<?php

namespace App\Models;

use App\Events\AddonSettingsProcessed;
use App\Models\WarcraftLogs\Report;
use App\Services\Blizzard\CharacterService;
use App\Services\Blizzard\GuildService;
use App\Services\Blizzard\PlayableClassService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;

class Character extends Model
{
    use HasFactory, Prunable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'characters';

    /**
     * The attributes that are the model's default values.
     *
     * @var array
     */
    protected $attributes = [
        'is_loot_councillor' => false,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_main' => 'boolean',
        'is_loot_councillor' => 'boolean',
        'reached_level_cap_at' => 'datetime',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'updated' => AddonSettingsProcessed::class,
        'deleted' => AddonSettingsProcessed::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        'is_main',
        'is_loot_councillor',
        'reached_level_cap_at',
    ];

    /**
     * All of the relationships to be touched.
     *
     * @var array
     */
    protected $touches = ['linkedCharacters'];

    /**
     * Get the guild rank associated with the character.
     */
    public function rank(): BelongsTo
    {
        return $this->belongsTo(GuildRank::class, 'rank_id');
    }

    /**
     * Get the main character from the linked characters.
     */
    protected function mainCharacter(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->linkedCharacters()->where('is_main', true)->first(),
        );
    }

    /**
     * Get the characters linked to this character.
     *
     * This relationship is defined in both directions to allow for easy retrieval of linked characters regardless of the direction of the link.
     */
    public function linkedCharacters(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'character_links', 'linked_character_id', 'character_id');
    }

    public function playableClass(): Attribute
    {
        return Attribute::make(
            get: function () {
                $characterService = app(CharacterService::class);
                $characterData = $characterService->getProfile($this->name);
                if (Arr::has($characterData, 'character_class.id')) {
                    $playableClassService = app(PlayableClassService::class);
                    $playableClass = $playableClassService->find(Arr::get($characterData, 'character_class.id'));

                    return [
                        'id' => Arr::get($characterData, 'character_class.id'),
                        'name' => Arr::get($playableClass, 'name'),
                        'icon_url' => $playableClassService->iconUrl(Arr::get($characterData, 'character_class.id')) ?? null,
                    ];
                }

                return null;
            }
        );
    }

    /**
     * Get the Warcraft Logs reports this character participated in.
     */
    public function warcraftLogsReports(): BelongsToMany
    {
        return $this->belongsToMany(Report::class, 'pivot_characters_wcl_reports', 'character_id', 'wcl_report_code')
            ->withPivot('presence');
    }

    /**
     * Get the prunable model query.
     */
    public function prunable(): Builder
    {
        $guildService = app(GuildService::class);
        $memberIds = $guildService->members()->pluck('character.id')->toArray();

        return static::whereNotIn('id', $memberIds)->where('updated_at', '<=', now()->subDays(14));
    }
}
