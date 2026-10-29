<?php

namespace Tests\Feature\Actions\Characters;

use App\Actions\Characters\MatchCharacterName;
use App\Exceptions\MultipleCharactersFoundException;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('characters')]
class MatchCharacterNameTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_strips_the_surname_without_surnames(): void
    {
        $thrall = $this->character('Thrall');

        $this->assertSame($thrall, MatchCharacterName::run('Thrall Stormrage', collect([$thrall]), usesSurnames: false));
    }

    #[Test]
    public function it_trims_the_input_without_surnames(): void
    {
        $thrall = $this->character('Thrall');

        $this->assertSame($thrall, MatchCharacterName::run("  Thrall\t", collect([$thrall]), usesSurnames: false));
    }

    #[Test]
    public function it_matches_the_whole_name_with_surnames(): void
    {
        $thrall = $this->character('Thrall Stormrage');

        $this->assertSame($thrall, MatchCharacterName::run('Thrall Stormrage', collect([$thrall]), usesSurnames: true));
        $this->assertSame($thrall, MatchCharacterName::run('thrall stormrage', collect([$thrall]), usesSurnames: true));
    }

    #[TestWith(['Thrall'], 'bare first name')]
    #[TestWith(['Thrall Bloodhoof'], 'another surname')]
    #[Test]
    public function it_does_not_match_part_of_a_name_with_surnames(string $input): void
    {
        $thrall = $this->character('Thrall Stormrage');

        $this->assertNull(MatchCharacterName::run($input, collect([$thrall]), usesSurnames: true));
    }

    #[Test]
    public function it_matches_the_whole_name_by_default(): void
    {
        $thrall = $this->character('Thrall Stormrage');

        $this->assertNull(MatchCharacterName::run('Thrall', collect([$thrall])));
        $this->assertSame($thrall, MatchCharacterName::run('Thrall Stormrage', collect([$thrall])));
    }

    // ==================== tiers ====================

    #[Test]
    public function it_prefers_an_exact_match_over_an_accent_folded_one(): void
    {
        $tears = $this->character('Tears');
        $accentedTears = $this->character('Teärs');

        $this->assertSame($tears, MatchCharacterName::run('Tears', collect([$accentedTears, $tears]), usesSurnames: false));
    }

    #[Test]
    public function it_trims_the_input_before_matching_exactly_with_surnames(): void
    {
        $tears = $this->character('Tears Moon');
        $accentedTears = $this->character('Teärs Moon');

        $this->assertSame($tears, MatchCharacterName::run(" Tears Moon\t", collect([$accentedTears, $tears]), usesSurnames: true));
    }

    #[Test]
    public function it_trims_stored_names_before_matching_exactly(): void
    {
        $tears = $this->character(' Tears ');
        $accentedTears = $this->character('Teärs');

        $this->assertSame($tears, MatchCharacterName::run('Tears', collect([$accentedTears, $tears]), usesSurnames: false));
    }

    #[Test]
    public function it_throws_with_every_character_matched_once_case_and_accents_are_ignored(): void
    {
        $tears = $this->character('Tears');
        $accentedTears = $this->character('Teärs');

        try {
            MatchCharacterName::run('TEARS', collect([$tears, $accentedTears]), usesSurnames: false);
            $this->fail('Expected a MultipleCharactersFoundException.');
        } catch (MultipleCharactersFoundException $exception) {
            $this->assertSame([$tears, $accentedTears], $exception->characters->all());
        }
    }

    #[Test]
    public function it_ignores_case_and_accents_when_nothing_matches_exactly(): void
    {
        $deo = $this->character('Déo');

        $this->assertSame($deo, MatchCharacterName::run('deo', collect([$deo]), usesSurnames: false));
    }

    #[TestWith(['Pugwalker'], 'unknown name')]
    #[TestWith([''], 'empty string')]
    #[TestWith(["\xB1\x31"], 'invalid UTF-8')]
    #[Test]
    public function it_returns_null_for_a_name_no_candidate_has(string $input): void
    {
        $this->assertNull(MatchCharacterName::run($input, collect([$this->character('Thrall')]), usesSurnames: false));
    }

    // ==================== scope ====================

    #[Test]
    public function it_only_matches_the_candidates_it_is_given(): void
    {
        Character::factory()->create(['name' => 'Thrall']);

        $this->assertNull(MatchCharacterName::run('Thrall', collect(), usesSurnames: false));
    }

    #[Test]
    public function it_never_logs(): void
    {
        Log::spy();
        $candidates = collect([$this->character('Tears'), $this->character('Teärs')]);

        rescue(fn () => MatchCharacterName::run('TEARS', $candidates, usesSurnames: false), report: false);
        MatchCharacterName::run('Pugwalker', $candidates, usesSurnames: false);

        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('info');
    }

    // ==================== helpers ====================

    private function character(string $name): Character
    {
        return Character::factory()->make(['name' => $name]);
    }
}
