<?php

use App\Http\Middleware\AddServerTimeHeader;
use App\Http\Middleware\EnsureEnvironment;
use App\Http\Middleware\EnsureTransientToken;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InflateGzipRequest;
use App\Http\Middleware\RemembersCurrentGameVersion;
use App\Http\Middleware\TouchDesktopClient;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CreateFreshApiToken;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn () => Route::middleware('throttle:60,1')->prefix('oauth')->name('passport.')->group(base_path('routes/oauth.php')),
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:api', EnsureTransientToken::class]],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            RemembersCurrentGameVersion::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            CreateFreshApiToken::class,
        ]);

        $middleware->alias([
            'env' => EnsureEnvironment::class,
        ]);

        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: AddServerTimeHeader::class);
        $middleware->appendToPriorityList(after: ThrottleRequestsWithRedis::class, append: InflateGzipRequest::class);
        $middleware->appendToPriorityList(after: Authorize::class, append: TouchDesktopClient::class);

        $isGzipEncoded = fn (Request $request): bool => strtolower((string) $request->header('Content-Encoding')) === 'gzip';

        TrimStrings::skipWhen($isGzipEncoded);
        ConvertEmptyStringsToNull::skipWhen($isGzipEncoded);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
