<?php

namespace Tests\Feature\Data\Attendance;

use App\Data\Attendance\BenchedCharacterData;
use App\Enums\Presence;
use App\Models\CharacterReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('raiding')]
class BenchedCharacterDataTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    #[Test]
    public function it_takes_the_tag_name_and_character_from_the_character_report(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $character = $this->createCharacter($gameVersion);
        $this->attend($this->createReport($gameVersion, now(), $this->createTag($gameVersion, attributes: ['name' => 'Raid'])), $character, Presence::Benched);

        $benched = BenchedCharacterData::fromCharacterReport(CharacterReport::sole());

        $this->assertSame('Raid', $benched->tag);
        $this->assertTrue($benched->character->is($character));
    }

    #[Test]
    public function it_names_an_untagged_reports_tag_untagged(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $report = $this->createReport($gameVersion, now());
        $report->update(['guild_tag_id' => null]);
        $this->attend($report, $this->createCharacter($gameVersion), Presence::Benched);

        $this->assertSame('Untagged', BenchedCharacterData::fromCharacterReport(CharacterReport::sole())->tag);
    }

    #[Test]
    public function it_emits_the_character_as_its_resource(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $character = $this->createCharacter($gameVersion, attributes: ['name' => 'Kael']);
        $this->attend($this->createReport($gameVersion, now()), $character, Presence::Benched);

        $array = BenchedCharacterData::fromCharacterReport(CharacterReport::sole())->toArray();

        $this->assertSame(['tag', 'character'], array_keys($array));
        $this->assertSame('Kael', $array['character']['name']);
    }
}
