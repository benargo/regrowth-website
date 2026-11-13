<?php

namespace Tests\Unit\Http\Resources\GuildRosterManager;

use App\Http\Resources\GuildRosterManager\TargetResource;
use App\Models\GameVersion;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class TargetResourceTest extends TestCase
{
    #[Test]
    #[Group('resource')]
    public function it_returns_the_target_shape_for_a_forever_version(): void
    {
        config(['services.grm.clock_tolerance_seconds' => 90]);
        $gameVersion = GameVersion::factory()->withWowClientFolder('_forever_')->make([
            'title' => 'Forever',
            'slug' => 'forever',
            'guild_name' => 'Regrowth',
            'blizzard_namespace' => 'forever',
        ]);

        $this->assertEquals([
            'id' => 'forever',
            'type' => 'targets',
            'attributes' => (object) [
                'title' => 'Forever',
                'clientFolder' => '_forever_',
                'guildKey' => 'Regrowth',
                'clockToleranceSeconds' => 90,
                'savedVariablesGlob' => '_forever_/WTF/Account/*/SavedVariables/Guild_Roster_Manager.lua',
            ],
        ], (new TargetResource($gameVersion))->resolve(new Request)['data']);
    }

    #[Test]
    #[Group('resource')]
    public function it_builds_the_saved_variables_glob_from_the_client_folder(): void
    {
        $gameVersion = GameVersion::factory()->withWowClientFolder('_anniversary_')->make();

        $this->assertSame(
            '_anniversary_/WTF/Account/*/SavedVariables/Guild_Roster_Manager.lua',
            (new TargetResource($gameVersion))->resolve(new Request)['data']['attributes']->savedVariablesGlob,
        );
    }
}
