<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\ListBenchedCharacters;
use App\Data\Attendance\BenchedCharacterData;
use App\Enums\Presence;
use App\Models\PlayableClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('raiding')]
class ListBenchedCharactersTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
    }

    #[Test]
    public function it_groups_benched_characters_by_tag_name(): void
    {
        $gameVersion = $this->makeAttendanceVersion();
        $kael = $this->makeCharacter($gameVersion, attributes: ['name' => 'Kael']);
        $illidan = $this->makeCharacter($gameVersion, attributes: ['name' => 'Illidan']);
        $this->attend($this->makeReport($gameVersion, now()->subDays(2), $this->makeTag($gameVersion, attributes: ['name' => 'Tuesday'])), $illidan, Presence::Benched);
        $this->attend($this->makeReport($gameVersion, now()->subDays(1), $this->makeTag($gameVersion, attributes: ['name' => 'Monday'])), $kael, Presence::Benched);

        $benched = ListBenchedCharacters::run($gameVersion, now()->subDays(7));

        $this->assertSame(['Monday', 'Tuesday'], $benched->keys()->all());
        $this->assertSame('Kael', $benched->get('Monday')->sole()->character->name);
        $this->assertSame('Illidan', $benched->get('Tuesday')->sole()->character->name);
    }

    #[Test]
    public function it_lists_each_character_once_sorted_by_name(): void
    {
        $gameVersion = $this->makeAttendanceVersion();
        $tag = $this->makeTag($gameVersion, attributes: ['name' => 'Raid']);
        $zed = $this->makeCharacter($gameVersion, attributes: ['name' => 'Zed']);
        $amy = $this->makeCharacter($gameVersion, attributes: ['name' => 'Amy']);
        foreach ([1, 2] as $daysAgo) {
            $report = $this->makeReport($gameVersion, now()->subDays($daysAgo), $tag);
            $this->attend($report, $zed, Presence::Benched);
            $this->attend($report, $amy, Presence::Benched);
        }

        $this->assertSame(
            ['Amy', 'Zed'],
            ListBenchedCharacters::run($gameVersion, now()->subDays(7))->get('Raid')->map(fn (BenchedCharacterData $benched): string => $benched->character->name)->all(),
        );
    }

    #[Test]
    public function it_groups_untagged_reports_under_untagged(): void
    {
        $gameVersion = $this->makeAttendanceVersion();
        $report = $this->makeReport($gameVersion, now()->subDay());
        $report->update(['guild_tag_id' => null]);
        $this->attend($report, $this->makeCharacter($gameVersion), Presence::Benched);

        $this->assertTrue(ListBenchedCharacters::run($gameVersion, now()->subDays(7))->has('Untagged'));
    }

    #[Test]
    public function it_ignores_reports_before_since_other_presences_and_other_versions(): void
    {
        $gameVersion = $this->makeAttendanceVersion();
        $otherVersion = $this->makeAttendanceVersion();
        $this->attend($this->makeReport($gameVersion, now()->subDays(8)), $this->makeCharacter($gameVersion), Presence::Benched);
        $this->attend($this->makeReport($gameVersion, now()->subDay()), $this->makeCharacter($gameVersion), Presence::Present);
        $this->attend($this->makeReport($otherVersion, now()->subDay()), $this->makeCharacter($otherVersion), Presence::Benched);

        $this->assertTrue(ListBenchedCharacters::run($gameVersion, now()->subDays(7))->isEmpty());
    }

    #[Test]
    public function it_includes_each_characters_playable_class(): void
    {
        $gameVersion = $this->makeAttendanceVersion();
        $character = $this->makeCharacter($gameVersion, attributes: ['playable_class_id' => PlayableClass::factory()->create(['name' => 'Mage'])->id]);
        $this->attend($this->makeReport($gameVersion, now()->subDay(), $this->makeTag($gameVersion, attributes: ['name' => 'Raid'])), $character, Presence::Benched);

        $this->assertSame('Mage', ListBenchedCharacters::run($gameVersion, now()->subDays(7))->get('Raid')->sole()->character->playableClass->name);
    }
}
