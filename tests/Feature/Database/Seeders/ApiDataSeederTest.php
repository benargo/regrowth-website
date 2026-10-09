<?php

namespace Tests\Feature\Database\Seeders;

use Database\Seeders\ApiDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
#[Group('discord-integration')]
#[Group('raidhelper-integration')]
#[Group('warcraftlogs-integration')]
class ApiDataSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    private array $invokedCommands = [];

    #[Test]
    public function it_runs_no_commands_when_the_flag_is_disabled(): void
    {
        config(['app.seed_from_apis' => false]);
        $this->stubFetchCommands();

        $this->artisan('db:seed', ['--class' => ApiDataSeeder::class])->assertSuccessful();

        $this->assertSame([], $this->invokedCommands);
    }

    #[Test]
    public function it_runs_every_fetch_command_in_order_when_the_flag_is_enabled(): void
    {
        config(['app.seed_from_apis' => true]);
        $this->stubFetchCommands();

        $this->artisan('db:seed', ['--class' => ApiDataSeeder::class])->assertSuccessful();

        $this->assertSame([
            'sync:discord',
            'fetch:blizzard-roster',
            'fetch:warcraft-logs',
            'fetch:raid-helper',
        ], $this->invokedCommands);
    }

    #[Test]
    public function it_warns_and_continues_when_a_command_fails(): void
    {
        config(['app.seed_from_apis' => true]);
        $this->stubFetchCommands(failing: 'fetch:blizzard-roster');

        $this->artisan('db:seed', ['--class' => ApiDataSeeder::class])
            ->expectsOutputToContain('fetch:blizzard-roster failed')
            ->assertSuccessful();

        $this->assertSame([
            'sync:discord',
            'fetch:blizzard-roster',
            'fetch:warcraft-logs',
            'fetch:raid-helper',
        ], $this->invokedCommands);
    }

    #[Test]
    public function it_warns_and_continues_when_a_command_throws(): void
    {
        config(['app.seed_from_apis' => true]);
        $this->stubFetchCommands(throwing: 'sync:discord');

        $this->artisan('db:seed', ['--class' => ApiDataSeeder::class])
            ->expectsOutputToContain('sync:discord failed')
            ->assertSuccessful();

        $this->assertContains('fetch:raid-helper', $this->invokedCommands);
    }

    // ==================== helpers ====================

    private function stubFetchCommands(?string $failing = null, ?string $throwing = null): void
    {
        $invokedCommands = &$this->invokedCommands;

        foreach (['sync:discord', 'fetch:blizzard-roster', 'fetch:warcraft-logs', 'fetch:raid-helper'] as $name) {
            Artisan::command($name, function () use ($name, $failing, $throwing, &$invokedCommands): int {
                $invokedCommands[] = $name;

                if ($name === $throwing) {
                    throw new \RuntimeException('boom');
                }

                return $name === $failing ? 1 : 0;
            });
        }
    }
}
