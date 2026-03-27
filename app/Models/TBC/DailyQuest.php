<?php

namespace App\Models\TBC;

use App\Enums\Instance;
use Database\Factories\TBC\DailyQuestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyQuest extends Model
{
    /** @use HasFactory<DailyQuestFactory> */
    use HasFactory;

    protected $table = 'tbc_daily_quests';

    protected $fillable = [
        'name',
        'type',
        'instance',
        'mode',
        'rewards',
    ];

    protected function casts(): array
    {
        return [
            'instance' => Instance::class,
            'rewards' => 'json',
        ];
    }
}
