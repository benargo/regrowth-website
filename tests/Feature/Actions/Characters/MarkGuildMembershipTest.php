<?php

namespace Tests\Feature\Actions\Characters;

use App\Actions\Characters\MarkGuildMembership;
use App\Enums\RankSource;
use App\Models\Character;
use App\Models\GameVersion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class MarkGuildMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function observedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC');
    }

    private function local(CarbonImmutable $instant): CarbonImmutable
    {
        return $instant->setTimezone(config('app.timezone'));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_marks_missing_characters_as_left(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $present = Character::factory()->for($gameVersion)->state(['name' => 'TesterA'])->create();
        $missing = Character::factory()->for($gameVersion)->state(['name' => 'TesterB'])->create();

        MarkGuildMembership::run($gameVersion, collect([$present->id]), $this->observedAt());

        $this->assertNull($present->fresh()->left_guild_at);
        $this->assertTrue($missing->fresh()->left_guild_at->equalTo($this->observedAt()));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_left_guild_at_as_the_same_instant_in_the_app_timezone(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $present = Character::factory()->for($gameVersion)->state(['name' => 'TesterC'])->create();
        $missing = Character::factory()->for($gameVersion)->state(['name' => 'TesterD'])->create();

        MarkGuildMembership::run($gameVersion, collect([$present->id]), $this->observedAt());

        $this->assertSame(
            $this->observedAt()->getTimestamp(),
            $missing->fresh()->left_guild_at->getTimestamp(),
        );
        $this->assertSame(
            $this->observedAt()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            $missing->fresh()->left_guild_at->format('Y-m-d H:i:s'),
        );
    }

    #[Group('happy-path')]
    #[Test]
    public function it_marks_returning_characters_as_in_the_guild(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $returning = Character::factory()->for($gameVersion)->state(['name' => 'TesterE'])->leftGuild($this->local($this->observedAt()->subDay()))->create();

        MarkGuildMembership::run($gameVersion, collect([$returning->id]), $this->observedAt());

        $this->assertNull($returning->fresh()->left_guild_at);
    }

    #[Group('edge-cases')]
    #[Test]
    public function it_marks_nobody_as_left_when_the_roster_is_empty(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $character = Character::factory()->for($gameVersion)->state(['name' => 'TesterF'])->create();

        MarkGuildMembership::run($gameVersion, collect(), $this->observedAt());

        $this->assertNull($character->fresh()->left_guild_at);
    }

    #[Group('edge-cases')]
    #[Test]
    public function it_leaves_other_game_versions_alone(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $otherVersion = GameVersion::factory()->create();
        $present = Character::factory()->for($gameVersion)->state(['name' => 'TesterG'])->create();
        $other = Character::factory()->for($otherVersion)->state(['name' => 'TesterH'])->create();
        $otherLeft = Character::factory()->for($otherVersion)->state(['name' => 'TesterI'])->leftGuild($this->local($this->observedAt()->subDay()))->create();

        MarkGuildMembership::run($gameVersion, collect([$present->id, $otherLeft->id]), $this->observedAt());

        $this->assertNull($other->fresh()->left_guild_at);
        $this->assertNotNull($otherLeft->fresh()->left_guild_at);
    }

    #[Group('edge-cases')]
    #[Test]
    public function it_keeps_a_character_with_newer_rank_evidence_in_the_guild(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $present = Character::factory()->for($gameVersion)->state(['name' => 'TesterJ'])->create();
        $newer = Character::factory()->for($gameVersion)->state(['name' => 'TesterK'])
            ->rankObserved($this->local($this->observedAt()->addHour()), RankSource::Grm)->create();
        $older = Character::factory()->for($gameVersion)->state(['name' => 'TesterL'])
            ->rankObserved($this->local($this->observedAt()->subHour()), RankSource::Grm)->create();

        MarkGuildMembership::run($gameVersion, collect([$present->id]), $this->observedAt());

        $this->assertNull($newer->fresh()->left_guild_at);
        $this->assertNotNull($older->fresh()->left_guild_at);
    }

    #[Group('edge-cases')]
    #[Test]
    public function it_keeps_a_character_who_left_after_the_response_as_left(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $leftAt = $this->local($this->observedAt()->addHour());
        $character = Character::factory()->for($gameVersion)->state(['name' => 'TesterM'])->leftGuild($leftAt)->create();

        MarkGuildMembership::run($gameVersion, collect([$character->id]), $this->observedAt());

        $this->assertTrue($character->fresh()->left_guild_at->equalTo($leftAt));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_flushes_the_characters_cache_tag_when_a_row_changes(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $present = Character::factory()->for($gameVersion)->state(['name' => 'TesterN'])->create();
        Character::factory()->for($gameVersion)->state(['name' => 'TesterO'])->create();
        Cache::tags(['characters'])->put('probe', 1);

        MarkGuildMembership::run($gameVersion, collect([$present->id]), $this->observedAt());

        $this->assertNull(Cache::tags(['characters'])->get('probe'));
    }
}
