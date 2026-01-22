<?php

namespace Tests\Feature\Models;

use App\Models\GuildConfig;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class GuildConfigTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return GuildConfig::class;
    }

    #[Test]
    public function it_persists_and_is_retrievable(): void
    {
        $row = $this->create([
            'name' => 'Regrowth',
            'realm_slug' => 'thunderstrike',
            'region' => 'eu',
            'warcraftlogs_guild_id' => 123456,
        ]);

        $this->assertTableHas(['id' => $row->id, 'name' => 'Regrowth']);
        $this->assertModelExists($row);
    }

    #[Test]
    public function it_has_expected_casts_and_fillable(): void
    {
        $model = new GuildConfig();

        $this->assertCasts($model, [
            'warcraftlogs_guild_id' => 'int',
            'blizzard_guild_id'     => 'int',
            'blizzard_realm_id'     => 'int',
        ]);

        $this->assertFillable($model, [
            'name','realm_slug','region',
            'warcraftlogs_guild_id',
            'blizzard_guild_id','blizzard_realm_id',
        ]);

        $this->assertGuarded($model, [
            'blizzard_namespace',
        ]);
    }

    #[Test]
    public function it_normalises_values_on_write_via_mutators(): void
    {
        $row = $this->create([
            'name' => 'Regrowth',
            'realm_slug' => 'Pyrewood Village', // should become 'pyrewood-village'
            'region' => 'EU',                  // should become 'eu'
            'blizzard_namespace' => 'Profile-Classic1x-EU', // should become 'profile-classic1x-eu'
            'warcraftlogs_guild_id' => 654321,
        ]);

        $this->assertTableHas([
            'id' => $row->id,
            'realm_slug' => 'pyrewood-village',
            'region' => 'eu',
            'blizzard_namespace' => 'profile-classic1x-eu',
        ]);
    }

    #[Test]
    public function it_exposes_virtual_realm_name_accessor(): void
    {
        $row = $this->create([
            'name' => 'Regrowth',
            'realm_slug' => 'pyrewood-village',
            'region' => 'eu',
            'warcraftlogs_guild_id' => 777777,
        ]);

        $this->assertSame('Pyrewood Village', $row->realm_name);
    }
}
