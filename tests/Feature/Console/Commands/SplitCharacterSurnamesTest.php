<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class SplitCharacterSurnamesTest extends TestCase
{
    use RefreshDatabase;

    // ==================== splitting ====================

    #[Test]
    public function it_splits_names_on_a_surname_version(): void
    {
        $character = $this->characterOnSurnameVersion('Thrall Stormrage');

        $this->artisan('app:split-character-surnames')->assertExitCode(0);

        $this->assertCharacter($character, 'Thrall', 'Stormrage');
    }

    #[Test]
    public function it_splits_on_the_first_space_only(): void
    {
        $character = $this->characterOnSurnameVersion('Tholomew Van Blackwell');

        $this->artisan('app:split-character-surnames')->assertExitCode(0);

        $this->assertCharacter($character, 'Tholomew', 'Van Blackwell');
    }

    #[Test]
    public function it_splits_two_characters_sharing_a_first_name(): void
    {
        $version = GameVersion::factory()->withSurnames()->create();
        $first = Character::factory()->create(['game_version_id' => $version->id, 'name' => 'Awak Blastington']);
        $second = Character::factory()->create(['game_version_id' => $version->id, 'name' => 'Awak Huntington']);

        $this->artisan('app:split-character-surnames')->assertExitCode(0);

        $this->assertCharacter($first, 'Awak', 'Blastington');
        $this->assertCharacter($second, 'Awak', 'Huntington');
    }

    #[Test]
    public function it_leaves_a_single_word_name_on_a_surname_version_unchanged(): void
    {
        $character = $this->characterOnSurnameVersion('Thrall');

        $this->artisan('app:split-character-surnames')->assertExitCode(0);

        $this->assertCharacter($character, 'Thrall', null);
    }

    #[Test]
    public function it_ignores_surrounding_and_repeated_whitespace(): void
    {
        $version = GameVersion::factory()->withSurnames()->create();
        $padded = Character::factory()->create(['game_version_id' => $version->id, 'name' => ' Thrall ']);
        $spaced = Character::factory()->create(['game_version_id' => $version->id, 'name' => 'Thrall  Stormrage']);

        $this->artisan('app:split-character-surnames')->assertExitCode(0);

        $this->assertCharacter($padded, 'Thrall', null);
        $this->assertCharacter($spaced, 'Thrall', 'Stormrage');
    }

    #[Test]
    public function it_splits_a_name_separated_by_a_non_breaking_space(): void
    {
        $character = $this->characterOnSurnameVersion("Thrall\u{00A0}Stormrage");

        $this->artisan('app:split-character-surnames')->assertExitCode(0);

        $this->assertCharacter($character, 'Thrall', 'Stormrage');
    }

    #[Test]
    public function it_leaves_characters_on_other_versions_unchanged(): void
    {
        $plainVersion = GameVersion::factory()->create();
        $onPlainVersion = Character::factory()->create(['game_version_id' => $plainVersion->id, 'name' => 'Genn Greymane']);
        $withoutVersion = Character::factory()->create(['game_version_id' => null, 'name' => 'Genn Greymane']);

        $this->artisan('app:split-character-surnames')->assertExitCode(0);

        $this->assertCharacter($onPlainVersion, 'Genn Greymane', null);
        $this->assertCharacter($withoutVersion, 'Genn Greymane', null);
    }

    #[Test]
    public function it_changes_nothing_on_a_second_run(): void
    {
        $character = $this->characterOnSurnameVersion('Thrall Stormrage');

        $this->artisan('app:split-character-surnames')->assertExitCode(0);
        $this->artisan('app:split-character-surnames')
            ->expectsOutputToContain('No character names to split.')
            ->assertExitCode(0);

        $this->assertCharacter($character, 'Thrall', 'Stormrage');
    }

    // ==================== reverting ====================

    #[Test]
    public function it_joins_each_surname_back_into_the_name_when_reverting(): void
    {
        $version = GameVersion::factory()->withSurnames()->create();
        $withSurname = Character::factory()->create(['game_version_id' => $version->id, 'name' => 'Thrall', 'surname' => 'Stormrage']);
        $withoutSurname = Character::factory()->create(['game_version_id' => $version->id, 'name' => 'Jaina', 'surname' => null]);

        $this->artisan('app:split-character-surnames --revert')
            ->expectsOutputToContain('Joined 1 character name.')
            ->assertExitCode(0);

        $this->assertCharacter($withSurname, 'Thrall Stormrage', null);
        $this->assertCharacter($withoutSurname, 'Jaina', null);
    }

    // ==================== output ====================

    #[Test]
    public function it_lists_each_split_in_a_table(): void
    {
        $version = GameVersion::factory()->withSurnames()->create(['title' => 'Anniversary']);
        $thrall = Character::factory()->create(['game_version_id' => $version->id, 'name' => 'Thrall Stormrage']);
        $jaina = Character::factory()->create(['game_version_id' => $version->id, 'name' => 'Jaina Proudmoore']);

        $this->artisan('app:split-character-surnames')
            ->expectsTable(['ID', 'Game version', 'Before', 'Name', 'Surname'], [
                [$thrall->id, 'Anniversary', 'Thrall Stormrage', 'Thrall', 'Stormrage'],
                [$jaina->id, 'Anniversary', 'Jaina Proudmoore', 'Jaina', 'Proudmoore'],
            ])
            ->expectsOutputToContain('Split 2 character names.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_uses_the_singular_noun_for_a_single_split(): void
    {
        $this->characterOnSurnameVersion('Thrall Stormrage');

        $this->artisan('app:split-character-surnames')
            ->expectsOutputToContain('Split 1 character name.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_lists_each_join_in_a_table_when_reverting(): void
    {
        $version = GameVersion::factory()->withSurnames()->create(['title' => 'Anniversary']);
        $character = Character::factory()->create(['game_version_id' => $version->id, 'name' => 'Thrall', 'surname' => 'Stormrage']);

        $this->artisan('app:split-character-surnames --revert')
            ->expectsTable(['ID', 'Game version', 'Name', 'Surname', 'After'], [
                [$character->id, 'Anniversary', 'Thrall', 'Stormrage', 'Thrall Stormrage'],
            ])
            ->expectsOutputToContain('Joined 1 character name.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_reports_when_there_is_nothing_to_split(): void
    {
        $this->artisan('app:split-character-surnames')
            ->expectsOutputToContain('No character names to split.')
            ->doesntExpectOutputToContain('Game version')
            ->assertExitCode(0);

        $this->artisan('app:split-character-surnames --revert')
            ->expectsOutputToContain('No character names to join.')
            ->assertExitCode(0);
    }

    // ==================== helpers ====================

    private function characterOnSurnameVersion(string $name): Character
    {
        $version = GameVersion::factory()->withSurnames()->create();

        return Character::factory()->create(['game_version_id' => $version->id, 'name' => $name]);
    }

    private function assertCharacter(Character $character, string $name, ?string $surname): void
    {
        $this->assertDatabaseHas('characters', ['id' => $character->id, 'name' => $name, 'surname' => $surname]);
    }
}
