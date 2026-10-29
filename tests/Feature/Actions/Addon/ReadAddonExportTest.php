<?php

namespace Tests\Feature\Actions\Addon;

use App\Actions\Addon\ReadAddonExport;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class ReadAddonExportTest extends TestCase
{
    use RefreshDatabase;

    private function exportPath(GameVersion $gameVersion): string
    {
        return "{$gameVersion->id}/export.json";
    }

    #[Test]
    public function it_returns_the_game_versions_export_with_the_user_injected(): void
    {
        Storage::fake('addon');
        $gameVersion = GameVersion::factory()->create();
        $user = User::factory()->create();
        Storage::disk('addon')->put($this->exportPath($gameVersion), json_encode(['system' => ['version' => 1], 'items' => [1, 2]]));

        $data = ReadAddonExport::run($gameVersion, $user);

        $this->assertSame([1, 2], $data['items']);
        $this->assertSame(1, $data['system']['version']);
        $this->assertSame(['id' => $user->id, 'name' => $user->displayName], $data['system']['user']);
    }

    #[Test]
    public function it_returns_null_when_the_export_has_not_been_built(): void
    {
        Storage::fake('addon');

        $this->assertNull(ReadAddonExport::run(GameVersion::factory()->create(), User::factory()->create()));
    }

    #[Test]
    public function it_returns_null_when_the_export_is_not_valid_json(): void
    {
        Storage::fake('addon');
        $gameVersion = GameVersion::factory()->create();
        Storage::disk('addon')->put($this->exportPath($gameVersion), '{"system":');

        $this->assertNull(ReadAddonExport::run($gameVersion, User::factory()->create()));
    }

    #[Test]
    public function it_does_not_read_another_game_versions_export(): void
    {
        Storage::fake('addon');
        $gameVersion = GameVersion::factory()->create();
        $other = GameVersion::factory()->create();
        Storage::disk('addon')->put($this->exportPath($other), json_encode(['system' => []]));

        $this->assertNull(ReadAddonExport::run($gameVersion, User::factory()->create()));
    }
}
