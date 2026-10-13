<?php

namespace Tests\Unit\Http\Resources;

use App\Data\PlannedAbsenceReasonData;
use App\Enums\PlannedAbsenceReason;
use App\Http\Resources\PlannedAbsenceResource;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

#[Group('raiding')]
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
        $this->assertArrayHasKey('notes', $array);
        $this->assertArrayNotHasKey('discord_message_id', $array);
        $this->assertArrayHasKey('created_by', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
        $this->assertArrayHasKey('deleted_at', $array);
        $this->assertArrayNotHasKey('permissions', $array);
    }

    // ==================== scalar fields ====================

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->create([
            'reason' => PlannedAbsenceReason::Holiday,
            'notes' => 'I will be on holiday.',
        ]);

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertSame($absence->id, $array['id']);
        $this->assertEquals(PlannedAbsenceReasonData::fromReason(PlannedAbsenceReason::Holiday), $array['reason']);
        $this->assertSame('I will be on holiday.', $array['notes']);
    }

    // ==================== character relation ====================

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
    public function it_includes_character_playable_class_when_loaded(): void
    {
        $this->mockRequestUser();

        $playableClass = PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);
        $character = Character::factory()->withPlayableClass($playableClass)->create(['name' => 'Thrall']);
        $absence = PlannedAbsence::factory()->create(['character_id' => $character->id]);
        $absence->load('character.playableClass');

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertIsArray($array['character']);
        $this->assertArrayHasKey('playable_class', $array['character']);
        $this->assertSame(1, $array['character']['playable_class']['id']);
        $this->assertSame('Warrior', $array['character']['playable_class']['name']);
    }

    #[Test]
    public function it_returns_null_playable_class_when_character_has_none(): void
    {
        $this->mockRequestUser();

        $character = Character::factory()->create(['name' => 'Thrall']);
        $absence = PlannedAbsence::factory()->create(['character_id' => $character->id]);
        $absence->load('character.playableClass');

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertIsArray($array['character']);
        $this->assertArrayHasKey('playable_class', $array['character']);
        $this->assertNull($array['character']['playable_class']);
    }

    // ==================== character portrait ====================

    #[Test]
    #[Group('media')]
    public function it_embeds_the_character_portrait_url_when_media_is_loaded(): void
    {
        $this->mockRequestUser();
        Storage::fake('public');

        $character = Character::factory()->create();
        $character->addMediaFromString('BINARY')
            ->usingFileName('character_15678.jpg')
            ->toMediaCollection(Character::MEDIA_COLLECTION);
        $absence = PlannedAbsence::factory()->create(['character_id' => $character->id]);
        $absence->load('character.media', 'character.playableClass');

        $array = (new PlannedAbsenceResource($absence))->resolve(new Request);

        $this->assertSame($character->getFirstMediaUrl(Character::MEDIA_COLLECTION), $array['character']['portrait_url']);
    }

    #[Test]
    public function it_omits_the_character_portrait_url_when_media_is_not_loaded(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->withCharacter()->create();
        $absence->load('character');

        $array = (new PlannedAbsenceResource($absence))->resolve(new Request);

        $this->assertArrayNotHasKey('portrait_url', $array['character']);
    }

    // ==================== meta ====================

    #[Test]
    public function meta_has_started_from_the_start_date(): void
    {
        $this->mockRequestUser();
        $this->freezeTime();

        $this->assertTrue($this->meta(['start_date' => today()->subDay()])['has_started']);
        $this->assertTrue($this->meta(['start_date' => today()])['has_started']);
        $this->assertFalse($this->meta(['start_date' => today()->addDay()])['has_started']);
    }

    #[Test]
    public function meta_has_ended_once_the_end_date_has_passed(): void
    {
        $this->mockRequestUser();
        $this->freezeTime();

        $this->assertTrue($this->meta(['start_date' => today()->subDays(3), 'end_date' => today()->subDay()])['has_ended']);
        $this->assertFalse($this->meta(['start_date' => today()->subDays(3), 'end_date' => today()])['has_ended']);
    }

    #[Test]
    public function meta_has_ended_from_the_start_date_when_open_ended(): void
    {
        $this->mockRequestUser();
        $this->freezeTime();

        $this->assertTrue($this->meta(['start_date' => today()->subDay(), 'end_date' => null])['has_ended']);
        $this->assertFalse($this->meta(['start_date' => today(), 'end_date' => null])['has_ended']);
    }

    // ==================== user relation ====================

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

    // ==================== created by relation ====================

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

    // ==================== end date and discord message id ====================

    #[Test]
    public function it_returns_null_end_date_when_not_set(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->withoutEndDate()->create();

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertNull($array['end_date']);
    }

    #[Test]
    public function it_returns_null_notes_when_not_set(): void
    {
        $this->mockRequestUser();

        $absence = PlannedAbsence::factory()->create(['notes' => null]);

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertNull($array['notes']);
    }

    // ==================== date formatting ====================

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

    // ==================== playable class icon url ====================

    #[Test]
    public function it_includes_icon_url_in_playable_class(): void
    {
        $playableClass = PlayableClass::factory()->create(['name' => 'Warrior']);
        $character = Character::factory()->for($playableClass, 'playableClass')->create();
        $absence = PlannedAbsence::factory()->for($character)->create();
        $absence->load('character.playableClass');

        $array = (new PlannedAbsenceResource($absence))->toArray(new Request);

        $this->assertArrayHasKey('icon_url', $array['character']['playable_class']);
        $this->assertArrayHasKey('slug', $array['character']['playable_class']);
    }

    // ==================== permissions metadata ====================

    #[Test]
    public function with_returns_the_permission_flags(): void
    {
        $absence = PlannedAbsence::factory()->create();

        $with = (new PlannedAbsenceResource($absence))->with($this->requestFor(User::factory()->create()));

        $this->assertSame(['edit', 'delete', 'restore', 'backdate'], array_keys($with['permissions']));
    }

    #[Test]
    public function with_allows_restore_for_admins(): void
    {
        $absence = PlannedAbsence::factory()->create();

        $with = (new PlannedAbsenceResource($absence))->with($this->requestFor(User::factory()->admin()->create()));

        $this->assertTrue($with['permissions']['restore']);
    }

    #[Test]
    public function with_denies_restore_for_non_admins(): void
    {
        $absence = PlannedAbsence::factory()->create();

        $with = (new PlannedAbsenceResource($absence))->with($this->requestFor(User::factory()->create()));

        $this->assertFalse($with['permissions']['restore']);
    }

    #[Test]
    public function with_allows_backdate_with_backdate_permission(): void
    {
        $absence = PlannedAbsence::factory()->create();

        $with = (new PlannedAbsenceResource($absence))->with($this->requestFor($this->userWithPermission('backdate-planned-absences')));

        $this->assertTrue($with['permissions']['backdate']);
    }

    #[Test]
    public function with_denies_backdate_without_permission(): void
    {
        $absence = PlannedAbsence::factory()->create();

        $with = (new PlannedAbsenceResource($absence))->with($this->requestFor(User::factory()->create()));

        $this->assertFalse($with['permissions']['backdate']);
    }

    #[Test]
    public function with_allows_edit_and_delete_for_the_creator_before_the_absence_starts(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $absence = PlannedAbsence::factory()->create(['created_by' => $user->id, 'start_date' => today()->addDay()]);

        $with = (new PlannedAbsenceResource($absence))->with($this->requestFor($user));

        $this->assertTrue($with['permissions']['edit']);
        $this->assertTrue($with['permissions']['delete']);
    }

    #[Test]
    public function with_denies_edit_and_delete_for_the_creator_once_the_absence_has_started(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $absence = PlannedAbsence::factory()->create(['created_by' => $user->id, 'start_date' => today()->subDay()]);

        $with = (new PlannedAbsenceResource($absence))->with($this->requestFor($user));

        $this->assertFalse($with['permissions']['edit']);
        $this->assertFalse($with['permissions']['delete']);
    }

    #[Test]
    public function with_denies_everything_without_a_user(): void
    {
        $absence = PlannedAbsence::factory()->create();

        $with = (new PlannedAbsenceResource($absence))->with(new Request);

        $this->assertSame(['edit' => false, 'delete' => false, 'restore' => false, 'backdate' => false], $with['permissions']);
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{has_started: bool, has_ended: bool}
     */
    private function meta(array $attributes): array
    {
        $absence = PlannedAbsence::factory()->create($attributes);

        return (new PlannedAbsenceResource($absence))->resolve(new Request)['meta'];
    }

    private function requestFor(User $user): Request
    {
        $request = new Request;
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function userWithPermission(string $permission): User
    {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

        $role = DiscordRole::factory()->create();
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->discordRoles()->attach($role->id);
        $user->load('discordRoles.permissions');

        return $user;
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
