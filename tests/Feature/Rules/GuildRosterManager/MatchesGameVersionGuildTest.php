<?php

namespace Tests\Feature\Rules\GuildRosterManager;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Models\GameVersion;
use App\Rules\GuildRosterManager\MatchesGameVersionGuild;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class MatchesGameVersionGuildTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_accepts_the_guild_key_of_the_named_game_version(): void
    {
        GameVersion::factory()->fetchableRoster()->create([
            'slug' => 'forever',
            'guild_name' => 'Regrowth',
            'blizzard_namespace' => BlizzardNamespace::FOREVER,
        ]);

        $validator = $this->validate(['gameVersion' => 'forever', 'guildKey' => 'Regrowth']);

        $this->assertFalse($validator->fails());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_the_guild_key_of_another_game_version(): void
    {
        GameVersion::factory()->fetchableRoster()->create([
            'slug' => 'forever',
            'guild_name' => 'Regrowth',
            'blizzard_namespace' => BlizzardNamespace::FOREVER,
        ]);
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'anniversary', 'guild_name' => 'Regrowth']);

        $validator = $this->validate(['gameVersion' => 'forever', 'guildKey' => 'Regrowth-Thunderstrike']);

        $this->assertSame(["The guildKey does not match this game version's guild."], $validator->errors()->get('guildKey'));
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_any_guild_key_when_the_game_version_is_not_a_current_roster(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'anniversary', 'guild_name' => 'Regrowth']);

        $validator = $this->validate(['gameVersion' => 'retail', 'guildKey' => 'Regrowth-Thunderstrike']);

        $this->assertSame(["The guildKey does not match this game version's guild."], $validator->errors()->get('guildKey'));
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_any_guild_key_when_the_data_has_no_game_version(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'anniversary', 'guild_name' => 'Regrowth']);

        $validator = $this->validate(['guildKey' => 'Regrowth-Thunderstrike']);

        $this->assertSame(["The guildKey does not match this game version's guild."], $validator->errors()->get('guildKey'));
    }

    /**
     * @param  array<string, string>  $data
     */
    private function validate(array $data): ValidatorContract
    {
        return Validator::make($data, ['guildKey' => [new MatchesGameVersionGuild]]);
    }
}
