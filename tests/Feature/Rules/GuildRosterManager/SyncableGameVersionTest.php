<?php

namespace Tests\Feature\Rules\GuildRosterManager;

use App\Models\GameVersion;
use App\Rules\GuildRosterManager\SyncableGameVersion;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class SyncableGameVersionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_accepts_a_current_roster_with_a_wow_client_folder(): void
    {
        GameVersion::factory()->fetchableRoster()->withWowClientFolder('_classic_era_')->create(['slug' => 'anniversary']);

        $validator = $this->validate('anniversary');

        $this->assertFalse($validator->fails());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_current_roster_without_a_wow_client_folder(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'anniversary']);

        $validator = $this->validate('anniversary');

        $this->assertSame(['The selected game version is invalid.'], $validator->errors()->get('gameVersion'));
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_game_version_superseded_by_a_newer_roster(): void
    {
        GameVersion::factory()->fetchableRoster()->withWowClientFolder('_classic_era_')
            ->create(['slug' => 'newer', 'guild_name' => 'Regrowth', 'release_date' => now()->subWeek()]);
        GameVersion::factory()->fetchableRoster()->withWowClientFolder('_classic_era_')
            ->create(['slug' => 'older', 'guild_name' => 'Regrowth', 'release_date' => now()->subYear()]);

        $validator = $this->validate('older');

        $this->assertSame(['The selected game version is invalid.'], $validator->errors()->get('gameVersion'));
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_an_unknown_slug(): void
    {
        GameVersion::factory()->fetchableRoster()->withWowClientFolder('_classic_era_')->create(['slug' => 'anniversary']);

        $validator = $this->validate('retail');

        $this->assertSame(['The selected game version is invalid.'], $validator->errors()->get('gameVersion'));
    }

    private function validate(string $slug): ValidatorContract
    {
        return Validator::make(['gameVersion' => $slug], ['gameVersion' => [new SyncableGameVersion]]);
    }
}
