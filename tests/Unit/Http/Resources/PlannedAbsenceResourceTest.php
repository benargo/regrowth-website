<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PlannedAbsenceResource;
use App\Models\PlannedAbsence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlannedAbsenceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->create();

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('character', $array);
        $this->assertArrayHasKey('user', $array);
        $this->assertArrayHasKey('start_date', $array);
        $this->assertArrayHasKey('end_date', $array);
        $this->assertArrayHasKey('reason', $array);
        $this->assertArrayHasKey('discord_message_id', $array);
        $this->assertArrayHasKey('created_by', $array);
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $this->mockRequestUser();

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
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->create();

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['character']);
    }

    #[Test]
    public function it_includes_character_when_loaded(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->withCharacter()->create();
        $absence->load('character');

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertIsArray($array['character']);
        $this->assertSame($absence->character_id, $array['character']['id']);
    }

    #[Test]
    public function it_omits_user_when_not_loaded(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->create();

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['user']);
    }

    #[Test]
    public function it_includes_user_when_loaded(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->create();
        $absence->load('user');

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertIsArray($array['user']);
        $this->assertSame($absence->user->id, $array['user']['id']);
    }

    #[Test]
    public function it_returns_null_user_when_loaded_but_user_id_is_null(): void
    {
        $absence = PlannedAbsence::factory()->withoutUser()->create();
        $absence->load('user');

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertNull($array['user']);
    }

    #[Test]
    public function it_omits_created_by_when_not_loaded(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->create();

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['created_by']);
    }

    #[Test]
    public function it_includes_created_by_when_loaded(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->create();
        $absence->load('createdBy');

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertIsArray($array['created_by']);
        $this->assertSame($absence->createdBy->id, $array['created_by']['id']);
    }

    #[Test]
    public function it_returns_null_end_date_when_not_set(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->withoutEndDate()->create();

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertNull($array['end_date']);
    }

    #[Test]
    public function it_returns_null_discord_message_id_when_not_set(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->create();

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertNull($array['discord_message_id']);
    }

    #[Test]
    public function it_returns_discord_message_id_when_set(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->withDiscordMessageId()->create();

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertSame($absence->discord_message_id, $array['discord_message_id']);
    }

    #[Test]
    public function it_formats_start_date_as_d_m_y(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->create([
            'start_date' => Carbon::parse('2026-06-15 10:00:00'),
        ]);

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertSame('2026-06-15', $array['start_date']);
    }

    #[Test]
    public function it_formats_end_date_as_d_m_y_when_set(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->create([
            'end_date' => Carbon::parse('2026-06-20 10:00:00'),
        ]);

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertSame('2026-06-20', $array['end_date']);
    }

    /**
     * Helper method to mock the request user for testing authorization logic in the resource.
     *
     * @param  bool  $is_admin  Whether the mocked user should have admin privileges.
     */
    private function mockRequestUser(bool $is_admin = false): void
    {
        $this->user = $is_admin ? User::factory()->admin()->create() : User::factory()->create();

        Mockery::mock(Request::class, function (MockInterface $mock) {
            $mock->shouldReceive('user')->andReturn($this->user);
        });
    }
}
