<?php

namespace App\Models\TBC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Boss extends Model
{
    /** @use HasFactory<\Database\Factories\TBC\BossFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbc_bosses';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'raid_id',
        'encounter_order',
    ];

    /**
     * Get the raid that this boss belongs to.
     *
     * @return BelongsTo<Raid, $this>
     */
    public function raid(): BelongsTo
    {
        return $this->belongsTo(Raid::class);
    }
}
