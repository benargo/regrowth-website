<?php

namespace App\Models;

use App\Casts\AsDifficultyCollection;
use App\Casts\AsExpansion;
use App\Models\Raids\Report;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'name', 'difficulties', 'expansion', 'is_frozen'])]
#[Hidden(['created_at', 'updated_at'])]
#[Table(name: 'wcl_zones', keyType: 'int', incrementing: false)]
class Zone extends Model
{
    use HasFactory;

    /**
     * The attributes that are the model's default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_frozen' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulties' => AsDifficultyCollection::class,
            'expansion' => AsExpansion::class,
            'is_frozen' => 'boolean',
        ];
    }

    /**
     * Get the reports for the zone.
     *
     * @return HasMany<Report>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
