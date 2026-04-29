<?php

namespace App\Models;

use App\Enums\Instance;
use Database\Factories\DailyQuestFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(DailyQuestFactory::class)]
class DailyQuest extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbc_daily_quests';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'instance',
        'mode',
        'rewards',
    ];

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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'instance' => Instance::class,
        'rewards' => 'json',
    ];

    /**
     * Get the quest name with the instance name appended for dungeon quests.
     *
     * Using a plain method rather than an Eloquent accessor prevents the formatted
     * value from being cached in $attributes and re-appended after queue serialisation.
     */
    public function displayName(): string
    {
        if ($this->type === 'Dungeon' && $this->instance) {
            return "{$this->name} ({$this->instance->value})";
        }

        return $this->name;
    }
}
