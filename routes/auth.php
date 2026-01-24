<?php

use App\Http\Controllers\Auth\DiscordController;
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
