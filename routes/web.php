<?php

use App\Http\Controllers\Dashboard\AddonController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\GrmController;
use App\Http\Controllers\Dashboard\GuildRankController;
use App\Http\Controllers\Dashboard\PhaseController;
use App\Http\Controllers\GuildRosterController;
use App\Http\Controllers\Loot\LootController;
use App\Http\Controllers\LootCouncil\CommentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WarcraftLogs\GuildTagController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});

/**
 * Guild Roster
 */
Route::get('/roster', [GuildRosterController::class, 'index'])->name('roster.index');

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

/*
 * Officers' Dashboard
 */
Route::group(['prefix' => 'dashboard', 'as' => 'dashboard.', 'middleware' => ['auth', 'can:access-dashboard']], function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');

    /**
     * Addon management
     */
    Route::get('/addon/export', [AddonController::class, 'export'])->name('addon.export');
    Route::get('/addon/export/json', [AddonController::class, 'exportJson'])->name('addon.export.json');
    Route::get('/addon/export/schema', [AddonController::class, 'exportSchema'])->name('addon.export.schema');
    Route::get('/addon/settings', [AddonController::class, 'settings'])->name('addon.settings');
    Route::post('/addon/settings/councillors', [AddonController::class, 'addCouncillor'])->name('addon.settings.councillors.add');
    Route::delete('/addon/settings/councillors/{character}', [AddonController::class, 'removeCouncillor'])->name('addon.settings.councillors.remove');

    /**
     * Guild ranks management
     */
    Route::get('/manage-ranks', [GuildRankController::class, 'manageRanks'])->name('ranks.view');
    Route::post('/manage-ranks', [GuildRankController::class, 'updatePositions'])->name('ranks.update-positions');
    Route::put('/ranks/{guildRank}', [GuildRankController::class, 'update'])->name('ranks.update');
    Route::post('/ranks', [GuildRankController::class, 'store'])->name('ranks.store');
    Route::patch('/ranks/{guildRank}/count-attendance', [GuildRankController::class, 'toggleCountAttendance'])->name('ranks.toggle-attendance');
    Route::delete('/ranks/{guildRank}', [GuildRankController::class, 'destroy'])->name('ranks.destroy');

    /**
     * Phases management
     */
    Route::get('/manage-phases', [PhaseController::class, 'listAll'])->name('phases.view');
    Route::put('/phases/{phase}', [PhaseController::class, 'update'])->name('phases.update');
    Route::put('/phases/{phase}/guild-tags', [PhaseController::class, 'updateGuildTags'])->name('phases.guild-tags.update');

    /**
     * GRM data upload
     */
    Route::get('/grm-upload', [GrmController::class, 'showUploadForm'])->name('grm-upload.form');
    Route::post('/grm-upload', [GrmController::class, 'handleUpload'])->name('grm-upload.upload');
});

/**
 * Warcraft Logs Guild Tags Management
 */
Route::patch('/wcl/guild-tags/{guildTag}/count-attendance', [GuildTagController::class, 'toggleCountAttendance'])
    ->middleware('auth')
    ->name('wcl.guild-tags.toggle-attendance');

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
