<?php

use App\Http\Controllers\ServeIconController;

/**
 * Icon serving
 */
Route::get('/icons/{size}/{name}', ServeIconController::class)
    ->name('icons.show')
    ->where('size', '[0-9]+')
    ->where('name', '[a-z0-9_]+\.(jpg|png)');
