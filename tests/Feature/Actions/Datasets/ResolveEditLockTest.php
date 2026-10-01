<?php

namespace Tests\Feature\Actions\Datasets;

use App\Actions\Datasets\ResolveEditLock;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class ResolveEditLockTest extends TestCase
{
    use RefreshDatabase;

    #[Group('happy-path')]
    #[Test]
    public function it_gives_a_free_edit_lock_to_an_active_officer(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $officer = User::factory()->officer()->create();

        $props = ResolveEditLock::run($this->requestFrom($officer), $gameVersion);

        $this->assertTrue($props['canEdit']);
        $this->assertNull($props['editor']());
        $this->assertTrue($gameVersion->isLockedForEditingBy(User::factory()->officer()->create()));
    }

    #[Test]
    public function it_names_the_holder_when_another_officer_has_the_edit_lock(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $holder = User::factory()->officer()->create(['nickname' => 'Holder']);
        $gameVersion->acquireEditLock($holder);

        $props = ResolveEditLock::run($this->requestFrom(User::factory()->officer()->create()), $gameVersion);

        $this->assertFalse($props['canEdit']);
        $this->assertSame($holder->display_name, $props['editor']());
    }

    #[Test]
    public function it_does_not_take_a_free_edit_lock_on_an_idle_poll(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $props = ResolveEditLock::run($this->requestFrom(User::factory()->officer()->create(), idle: true), $gameVersion);

        $this->assertTrue($props['canEdit']);
        $this->assertFalse($gameVersion->isBeingEdited());
    }

    #[Test]
    public function it_reports_another_holder_to_an_idle_officer(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $holder = User::factory()->officer()->create();
        $gameVersion->acquireEditLock($holder);

        $props = ResolveEditLock::run($this->requestFrom(User::factory()->officer()->create(), idle: true), $gameVersion);

        $this->assertFalse($props['canEdit']);
        $this->assertSame($holder->display_name, $props['editor']());
    }

    // ==================== helpers ====================

    private function requestFrom(User $user, bool $idle = false): Request
    {
        $request = Request::create('/manage', 'GET', server: $idle ? ['HTTP_X_EDIT_IDLE' => '1'] : []);
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
