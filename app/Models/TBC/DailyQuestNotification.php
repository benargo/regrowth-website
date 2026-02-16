<?php

namespace App\Models\TBC;

use App\Events\DailyQuestNotificationCreated;
use App\Events\DailyQuestNotificationDeleting;
use App\Events\DailyQuestNotificationUpdated;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class DailyQuestNotification extends Model
{
    /** @use HasFactory<\Database\Factories\TBC\DailyQuestNotificationFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'tbc_daily_quest_notifications';

    protected $fillable = [
        'date',
        'discord_message_id',
        'cooking_quest_id',
        'fishing_quest_id',
        'dungeon_quest_id',
        'heroic_quest_id',
        'pvp_quest_id',
        'sent_by_user_id',
        'updated_by_user_id',
    ];

    protected $dispatchesEvents = [
        'created' => DailyQuestNotificationCreated::class,
        'updated' => DailyQuestNotificationUpdated::class,
        'deleting' => DailyQuestNotificationDeleting::class,
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function cookingQuest(): BelongsTo
    {
        return $this->belongsTo(DailyQuest::class, 'cooking_quest_id');
    }

    public function fishingQuest(): BelongsTo
    {
        return $this->belongsTo(DailyQuest::class, 'fishing_quest_id');
    }

    public function dungeonQuest(): BelongsTo
    {
        return $this->belongsTo(DailyQuest::class, 'dungeon_quest_id');
    }

    public function heroicQuest(): BelongsTo
    {
        return $this->belongsTo(DailyQuest::class, 'heroic_quest_id');
    }

    public function pvpQuest(): BelongsTo
    {
        return $this->belongsTo(DailyQuest::class, 'pvp_quest_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function currentDailyQuestDate(): Carbon
    {
        return now('Europe/Paris')->hour < 3
            ? now('Europe/Paris')->subDay()->setTime(3, 0, 0)
            : now('Europe/Paris')->setTime(3, 0, 0);
    }

    public static function existsForToday(): bool
    {
        $currentDate = self::currentDailyQuestDate();

        return self::where('date', $currentDate)->exists();
    }

    public static function getTodaysNotification(): ?self
    {
        $currentDate = self::currentDailyQuestDate();

        return self::where('date', $currentDate)->first();
    }

    public function getQuests(): Collection
    {
        return collect([
            $this->cookingQuest,
            $this->fishingQuest,
            $this->dungeonQuest,
            $this->heroicQuest,
            $this->pvpQuest,
        ])->filter();
    }
}
