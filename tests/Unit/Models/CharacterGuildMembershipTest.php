<?php

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\CharacterGuildMembership;
use Carbon\CarbonInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('characters')]
class CharacterGuildMembershipTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return CharacterGuildMembership::class;
    }

    #[Test]
    public function it_has_the_expected_fillable_attributes(): void
    {
        $this->assertFillableAttribute($this->make(), ['character_id', 'joined_on', 'left_on', 'verified']);
    }

    #[Test]
    public function it_casts_membership_dates_to_dates_without_a_time(): void
    {
        $membership = $this->create([
            'joined_on' => '2026-03-01 18:45:00',
            'left_on' => '2026-10-12',
        ])->fresh();

        $this->assertInstanceOf(CarbonInterface::class, $membership->joined_on);
        $this->assertInstanceOf(CarbonInterface::class, $membership->left_on);
        $this->assertSame('2026-03-01 00:00:00', $membership->joined_on->toDateTimeString());
        $this->assertSame('2026-10-12 00:00:00', $membership->left_on->toDateTimeString());
    }

    #[Test]
    public function it_allows_open_ended_and_undated_periods(): void
    {
        $membership = $this->create(['joined_on' => null, 'left_on' => null])->fresh();

        $this->assertNull($membership->joined_on);
        $this->assertNull($membership->left_on);
    }

    #[Test]
    public function it_casts_verified_to_a_boolean_that_defaults_to_false(): void
    {
        $membership = CharacterGuildMembership::create([
            'character_id' => Character::factory()->create()->id,
        ])->fresh();

        $this->assertFalse($membership->verified);

        $membership->update(['verified' => 1]);

        $this->assertTrue($membership->fresh()->verified);
    }

    #[Test]
    public function it_belongs_to_a_character(): void
    {
        $character = Character::factory()->create();
        $membership = $this->create(['character_id' => $character->id]);

        $this->assertTrue($membership->character->is($character));
        $this->assertTrue($character->guildMemberships->first()->is($membership));
    }

    #[Test]
    public function deleting_a_character_deletes_its_membership_periods(): void
    {
        $membership = $this->create();

        $membership->character->delete();

        $this->assertModelMissing($membership);
    }
}
