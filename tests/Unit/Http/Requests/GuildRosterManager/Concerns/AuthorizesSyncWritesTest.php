<?php

namespace Tests\Unit\Http\Requests\GuildRosterManager\Concerns;

use App\Http\Requests\GuildRosterManager\Concerns\AuthorizesSyncWrites;
use App\Models\DesktopClient;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionMethod;
use Tests\TestCase;

#[Group('characters')]
#[Group('authorization')]
class AuthorizesSyncWritesTest extends TestCase
{
    #[Test]
    #[TestWith([true])]
    #[TestWith([false])]
    public function can_write_sync_asks_whether_the_user_may_write_to_the_sync_from_the_desktop_client(bool $mayWriteSync): void
    {
        $sync = new Upload;
        $desktopClient = new DesktopClient;

        $request = $this->makeRequest($sync, $this->user([$sync, $desktopClient], $mayWriteSync));
        $request->attributes->set('desktopClient', $desktopClient);

        $this->assertSame($mayWriteSync, $this->canWriteSync($request));
    }

    #[Test]
    public function can_write_sync_passes_no_desktop_client_when_the_request_carries_none(): void
    {
        $sync = new Upload;

        $request = $this->makeRequest($sync, $this->user([$sync, null], mayWriteSync: true));

        $this->assertTrue($this->canWriteSync($request));
    }

    // ==================== helpers ====================

    /**
     * Mock a user who answers the `writeSync` ability for exactly the given arguments.
     *
     * @param  array{Upload, DesktopClient|null}  $arguments
     */
    private function user(array $arguments, bool $mayWriteSync): User
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->once()->with('writeSync', $arguments)->andReturn($mayWriteSync);

        return $user;
    }

    /**
     * Build a form request using the trait, bound to the sync and resolving the user.
     */
    private function makeRequest(Upload $sync, User $user): FormRequest
    {
        $request = new class extends FormRequest
        {
            use AuthorizesSyncWrites;
        };

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('sync', null)->andReturn($sync);

        $request->setUserResolver(fn (): User => $user);
        $request->setRouteResolver(fn (): Route => $route);

        return $request;
    }

    /**
     * Call the trait's protected `canWriteSync()` on the request.
     */
    private function canWriteSync(FormRequest $request): bool
    {
        return new ReflectionMethod($request, 'canWriteSync')->invoke($request);
    }
}
