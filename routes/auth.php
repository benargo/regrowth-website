<?php

use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\Auth\LocalLoginController;
use App\Http\Controllers\Auth\ViewAsRoleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Discord OAuth Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [DiscordController::class, 'redirect'])
        ->name('login');

    Route::get('auth/discord/callback', [DiscordController::class, 'callback'])
        ->name('discord.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [DiscordController::class, 'destroy'])
        ->name('logout');
});

/*
|--------------------------------------------------------------------------
| Manual Login Routes (local / testing only)
|--------------------------------------------------------------------------
|
| The routes are only registered outside production so they never reach
| Ziggy's serialised route collection. The env middleware is defence in
| depth: it 404s the request even if the routes are somehow registered
| in production (e.g. a route cache built in the wrong environment).
*/
if (app()->environment(['local', 'testing'])) {
    Route::middleware(['env:local,testing', 'guest'])->group(function () {
        Route::get('login/local', [LocalLoginController::class, 'create'])
            ->name('login.local');

        Route::post('login/local', [LocalLoginController::class, 'store'])
            ->name('login.local.store');
    });
}

/*
|--------------------------------------------------------------------------
| User account settings routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'account', 'as' => 'account.', 'middleware' => ['auth']], function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
});

/*
|--------------------------------------------------------------------------
| Impersonation routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'view-as', 'middleware' => ['auth']], function () {
    Route::get('/self', [ViewAsRoleController::class, 'stopViewingAs'])->name('auth.return-to-self');
    Route::get('/{role}', [ViewAsRoleController::class, 'viewAsRole'])->can('impersonate-roles')->name('auth.view-as');
});
