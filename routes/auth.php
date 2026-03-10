<?php

use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\DiscordController;
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
Route::group(['prefix' => 'view-as', 'middleware' => ['auth', 'can:impersonate-roles']], function () {
    Route::get('/self', [ViewAsRoleController::class, 'stopViewingAs'])->name('auth.return-to-self');
    Route::get('/{role}', [ViewAsRoleController::class, 'viewAsRole'])->name('auth.view-as');
});
