<?php

use Illuminate\Support\Facades\Route;

// All testing routes must go inside this file, so that they can be disabled in production.
if (app()->environment('testing')) {
    Route::get('/test-route', fn () => response('ok'))->name('test-route');
}
