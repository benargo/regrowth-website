<?php

namespace Tests\Unit\Http\Requests\GuildRosterManager;

use App\Http\Requests\GuildRosterManager\ExtendSyncRequest;
use App\Models\DesktopClient;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Routing\Route;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('characters')]
class ExtendSyncRequestTest extends TestCase
{
    #[Test]
    #[Group('authorization')]
    #[TestWith([true])]
    #[TestWith([false])]
    public function authorize_answers_whether_the_user_may_write_to_the_sync_from_the_desktop_client(bool $mayWriteSync): void
    {
        $sync = new Upload;
        $desktopClient = new DesktopClient;

        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->once()->with('writeSync', [$sync, $desktopClient])->andReturn($mayWriteSync);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('sync', null)->andReturn($sync);

        $request = ExtendSyncRequest::create('/', 'POST');
        $request->attributes->set('desktopClient', $desktopClient);
        $request->setUserResolver(fn (): User => $user);
        $request->setRouteResolver(fn (): Route => $route);

        $this->assertSame($mayWriteSync, $request->authorize());
    }
}
