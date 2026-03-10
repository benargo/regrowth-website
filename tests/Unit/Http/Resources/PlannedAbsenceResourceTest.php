<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PlannedAbsenceResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\CharacterResource;
use App\Models\PlannedAbsence;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlannedAbsenceResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $absence = PlannedAbsence::factory()->create();

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('character', $array);
        $this->assertArrayHasKey('start_date', $array);
        $this->assertArrayHasKey('end_date', $array);
        $this->assertArrayHasKey('reason', $array);
        $this->assertArrayHasKey('created_by', $array);
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $absence = PlannedAbsence::factory()->create([
            'reason' => 'I will be on holiday.',
        ]);

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertSame($absence->id, $array['id']);
        $this->assertSame('I will be on holiday.', $array['reason']);
    }

    #[Test]
    public function it_omits_character_when_not_loaded(): void
    {
        $absence = PlannedAbsence::factory()->create();

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['character']);
    }

    #[Test]
    public function it_includes_character_when_loaded(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();
        $absence->load('character');

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertInstanceOf(CharacterResource::class, $array['character']);
        $this->assertSame($absence->character_id, $array['character']->resource->id);
    }

    #[Test]
    public function it_omits_created_by_when_not_loaded(): void
    {
        $absence = PlannedAbsence::factory()->create();

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['created_by']);
    }

    #[Test]
    public function it_includes_created_by_when_loaded(): void
    {
        $absence = PlannedAbsence::factory()->create();
        $absence->load('createdBy');

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertInstanceOf(UserResource::class, $array['created_by']);
        $this->assertSame($absence->createdBy->id, $array['created_by']->resource->id);
    }

    #[Test]
    public function it_returns_null_end_date_when_not_set(): void
    {
        $absence = PlannedAbsence::factory()->withoutEndDate()->create();

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertNull($array['end_date']);
    }

    #[Test]
    public function it_formats_start_date_as_d_m_y(): void
    {
        $absence = PlannedAbsence::factory()->create([
            'start_date' => Carbon::parse('2026-06-15 10:00:00'),
        ]);

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertSame('2026-06-15', $array['start_date']);
    }

    #[Test]
    public function it_formats_end_date_as_d_m_y_when_set(): void
    {
        $absence = PlannedAbsence::factory()->create([
            'end_date' => Carbon::parse('2026-06-20 10:00:00'),
        ]);

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertSame('2026-06-20', $array['end_date']);
    }
}
