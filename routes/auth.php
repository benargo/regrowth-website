<?php

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
| View As Role Route
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'can:view-as-role'])->get('/view-as/{role}', [ViewAsRoleController::class, 'viewAsRole'])->name('auth.view-as');
Route::middleware(['auth'])->get('/return-to-self', [ViewAsRoleController::class, 'stopViewingAs'])->name('auth.return-to-self');
