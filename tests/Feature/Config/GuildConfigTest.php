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
    public function officers_are_configured(): void
    {
        $officers = config('guild.officers');

        $this->assertIsArray($officers);
        $this->assertNotEmpty($officers);
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

            $this->assertContains($officer['tier'] ?? null, ['council', 'officer'], "{$officer['name']} needs a tier");
            $this->assertArrayHasKey('role', $officer);
            if ($officer['tier'] === 'council') {
                $this->assertIsString($officer['role'], "{$officer['name']} is council and needs a role string");
            } else {
                $this->assertNull($officer['role'], "{$officer['name']} is an officer and has no role");
            }
            $this->assertMatchesRegularExpression('#^/images/[^/]+\.webp$#', $officer['image'] ?? '', "{$officer['name']} needs a web path under /images/");
            $this->assertFileExists(public_path(ltrim($officer['image'], '/')), "{$officer['name']}'s image file is missing");
            [$width, $height] = getimagesize(public_path(ltrim($officer['image'], '/')));
            $this->assertSame([$width, $height], [$officer['width'] ?? null, $officer['height'] ?? null], "{$officer['name']}'s width and height must match the image file");
            if (array_key_exists('flag', $officer)) {
                $this->assertMatchesRegularExpression('/^[a-z-]+$/', $officer['flag'], "{$officer['name']}'s flag must be a lowercase custom flag key");
            }
        }
    }

    #[Test]
    public function both_tiers_have_members(): void
    {
        $tiers = array_column(config('guild.officers'), 'tier');

        $this->assertContains('council', $tiers);
        $this->assertContains('officer', $tiers);
    }

    #[Test]
    public function officer_names_and_images_are_unique(): void
    {
        $officers = config('guild.officers');

        $this->assertSame(array_unique(array_column($officers, 'name')), array_column($officers, 'name'));
        $this->assertSame(array_unique(array_column($officers, 'image')), array_column($officers, 'image'));
    }
}
