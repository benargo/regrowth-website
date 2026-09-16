<?php

namespace Tests\Feature\Config;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('config')]
class GuildConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['guild.discord_invite_url' => 'https://discord.gg/example-invite']);
    }

    #[Test]
    public function forever_launch_date_is_configured(): void
    {
        $this->assertSame('2026-11-04T23:00:00Z', config('guild.forever_launch_at'));
    }

    #[Test]
    public function discord_invite_url_is_configured(): void
    {
        $this->assertSame('https://discord.gg/example-invite', config('guild.discord_invite_url'));
    }

    #[Test]
    public function all_eight_officers_are_configured(): void
    {
        $officers = config('guild.officers');

        $this->assertIsArray($officers);
        $this->assertCount(8, $officers);
    }

    #[Test]
    public function every_officer_has_the_required_shape(): void
    {
        foreach (config('guild.officers') as $officer) {
            $this->assertArrayHasKey('name', $officer);
            $this->assertArrayHasKey('nationality', $officer);
            $this->assertArrayHasKey('country_code', $officer);
            $this->assertArrayHasKey('demonym', $officer);

            $this->assertSame(3, strlen($officer['nationality']), "{$officer['name']} needs an ISO 3166-1 alpha-3 code");
            $this->assertSame(2, strlen($officer['country_code']), "{$officer['name']} needs an ISO 3166-1 alpha-2 code");
            $this->assertNotEmpty($officer['demonym'], "{$officer['name']} needs a demonym for screen readers");
        }
    }

    #[Test]
    public function officers_are_listed_alphabetically(): void
    {
        $names = array_column(config('guild.officers'), 'name');
        $sorted = $names;
        sort($sorted);

        $this->assertSame($sorted, $names);
    }
}
