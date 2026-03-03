<?php

namespace App\Models;

use App\Events\CharacterDeleted;
use App\Events\CharacterUpdated;
use App\Models\WarcraftLogs\Report;
use App\Services\Blizzard\Data\PlayableClass;
use App\Services\Blizzard\Data\PlayableRace;
use App\Services\Blizzard\Exceptions\InvalidClassException;
use App\Services\Blizzard\Exceptions\InvalidRaceException;
use App\Services\Blizzard\GuildService;
use App\Services\Blizzard\PlayableClassService;
use App\Services\Blizzard\PlayableRaceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

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
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'playable_class_id',
        'playable_race_id',
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
        'playable_class_id' => 'integer',
        'playable_race_id' => 'integer',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'updated' => CharacterUpdated::class,
        'deleted' => CharacterDeleted::class,
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
        'playable_class_id',
        'playable_race_id',
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
                if ($this->playable_class_id === null) {
                    return PlayableClass::unknown()->toArray();
                }

                try {
                    $service = app(PlayableClassService::class);
                    $data = $service->find($this->playable_class_id);

                    return new PlayableClass(
                        id: $this->playable_class_id,
                        name: Arr::get($data, 'name'),
                        icon_url: $service->iconUrl($this->playable_class_id),
                    )->toArray();
                } catch (RequestException|ConnectionException $e) {
                    Log::warning('Failed to fetch playable class for id '.$this->playable_class_id.': '.$e->getMessage());

                    return PlayableClass::unknown()->toArray();
                }
            },
            set: function (?int $id) {
                if ($id === null) {
                    return ['playable_class_id' => null];
                }

                try {
                    app(PlayableClassService::class)->find($id);
                } catch (InvalidClassException $e) {
                    throw $e;
                } catch (RequestException|ConnectionException $e) {
                    return ['playable_class_id' => $this->attributes['playable_class_id'] ?? null];
                }

                return ['playable_class_id' => $id];
            }
        );
    }

    public function playableRace(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->playable_race_id === null) {
                    return PlayableRace::unknown()->toArray();
                }

                try {
                    $service = app(PlayableRaceService::class);
                    $data = $service->find($this->playable_race_id);

                    return new PlayableRace(
                        id: $this->playable_race_id,
                        name: Arr::get($data, 'name'),
                    )->toArray();
                } catch (RequestException|ConnectionException $e) {
                    Log::warning('Failed to fetch playable race for id '.$this->playable_race_id.': '.$e->getMessage());

                    return PlayableRace::unknown()->toArray();
                }
            },
            set: function (?int $id) {
                if ($id === null) {
                    return ['playable_race_id' => null];
                }

                try {
                    app(PlayableRaceService::class)->find($id);
                } catch (InvalidRaceException $e) {
                    throw $e;
                } catch (RequestException|ConnectionException $e) {
                    return ['playable_race_id' => $this->attributes['playable_race_id'] ?? null];
                }

                return ['playable_race_id' => $id];
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
