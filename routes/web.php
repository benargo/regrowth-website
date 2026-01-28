<?php

use App\Http\Controllers\Loot\LootDashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});

/*
 * Officers' Dashboard
 */
Route::group(['prefix' => 'dashboard', 'as' => 'dashboard.', 'middleware' => ['auth', 'can:access-dashboard']], function () {
    Route::get('/', function () {
        return Inertia::render('Dashboard/Index');
    })->name('index');
});

/**
 * Loot Priority Manager
 */
Route::group(['prefix' => 'loot', 'middleware' => ['auth', 'can:access-loot']], function () {
    Route::get('/', [LootDashboardController::class, 'index'])->name('loot.index');
    Route::get('/items/{item}', [LootDashboardController::class, 'showItem'])->name('loot.items.show');
    Route::get('/edit/{item}', [LootDashboardController::class, 'editItem'])->middleware('can:edit-loot-priorities')->name('loot.items.edit');
    Route::put('/items/{item}/priorities', [LootDashboardController::class, 'updateItemPriorities'])->middleware('can:edit-loot-priorities')->name('loot.items.priorities.update');
});

/**
 * Comps spreadsheet redirect
 */
Route::get('/comps', function () {
    return redirect('https://docs.google.com/spreadsheets/d/1SYaMOFDtXxdRm7gQz6nG7c_B-N7rsf7P7QIRipkJkwg/view?pli=1&gid=934701754#gid=934701754', 303);
});

/**
 * Discord redirect
 */
Route::get('/discord', function () {
    return redirect('https://discord.gg/regrowth', 303);
});

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });

require __DIR__.'/auth.php';
