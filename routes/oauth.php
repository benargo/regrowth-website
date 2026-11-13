<?php

use App\Http\Middleware\BindDesktopClientTokenOnIssue;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;

/*
|--------------------------------------------------------------------------
| OAuth Token Routes
|--------------------------------------------------------------------------
|
| Registered outside the web group, with the `oauth` prefix and `passport.` name prefix.
*/

Route::post('token', [AccessTokenController::class, 'issueToken'])
    ->middleware(BindDesktopClientTokenOnIssue::class)
    ->name('token');
