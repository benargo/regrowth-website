<?php

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\PhaseController;
use App\Http\Controllers\Loot\LootController;
use App\Http\Controllers\LootCouncil\CommentController;
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
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('/addon/export', [DashboardController::class, 'exportAddonData'])->name('addon.export');
    Route::get('/manage-phases', [PhaseController::class, 'listAll'])->name('manage-phases');
    Route::put('/phases/{phase}', [PhaseController::class, 'update'])->name('phases.update');
});

/**
 * Loot Priority Manager
 */
Route::group(['prefix' => 'loot', 'middleware' => ['auth', 'can:access-loot']], function () {
    Route::get('/', [LootController::class, 'index'])->name('loot.index');
    Route::get('/items/{item}', [LootController::class, 'showItem'])->name('loot.items.show');
    Route::get('/edit/{item}', [LootController::class, 'editItem'])->middleware('can:edit-loot-items')->name('loot.items.edit');
    Route::put('/items/{item}/priorities', [LootController::class, 'updateItemPriorities'])->middleware('can:edit-loot-items')->name('loot.items.priorities.update');

    // Notes routes
    Route::post('/items/{item}/notes', [LootController::class, 'updateItemNotes'])->middleware('can:edit-loot-items')->name('loot.items.notes.store');

    // Comment routes
    Route::post('/items/{item}/comments', [CommentController::class, 'store'])->name('loot.items.comments.store');
    Route::put('/items/{item}/comments/{comment}', [CommentController::class, 'update'])->name('loot.items.comments.update');
    Route::delete('/items/{item}/comments/{comment}', [CommentController::class, 'destroy'])->name('loot.items.comments.destroy');
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
