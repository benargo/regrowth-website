<?php

namespace App\Models;

use App\Enums\Instance;
use Database\Factories\DailyQuestFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
     * Get the quest name.
     *
     * This accessor allows us to display the quest name as "Quest Name (Instance Name)" when the quest is a dungeon quest with an associated instance.
     * For non-dungeon quests, it will simply return the quest name.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($this->type === 'Dungeon' && $this->instance) {
                    return "{$value} ({$this->instance->value})";
                }

                return $value;
            }
        );
    }
}
