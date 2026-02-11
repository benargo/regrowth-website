<?php

use App\Http\Controllers\Dashboard\AddonController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\GrmController;
use App\Http\Controllers\Dashboard\GuildRankController;
use App\Http\Controllers\Dashboard\PhaseController;
use App\Http\Controllers\GuildRosterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LootCouncil\BiasToolController;
use App\Http\Controllers\LootCouncil\CommentController;
use App\Http\Controllers\LootCouncil\CommentReactionController;
use App\Http\Controllers\LootCouncil\ItemController;
use App\Http\Controllers\LootCouncil\NotesController;
use App\Http\Controllers\LootCouncil\PrioritiesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WarcraftLogs\GuildTagController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'home'])->name('home');

/**
 * Guild Roster
 */
Route::get('/roster', [GuildRosterController::class, 'index'])->name('roster.index');

/**
 * Loot Bias Tools
 */
Route::group(['prefix' => 'loot', 'middleware' => ['auth', 'can:viewAny,App\Models\LootCouncil\Item']], function () {
    Route::get('/', [BiasToolController::class, 'index'])->name('loot.index');
    Route::post('/items/{item}/comments', [CommentController::class, 'store'])->name('loot.items.comments.store');
    Route::post('/items/{item}/notes', [NotesController::class, 'update'])->can('update', 'item')->name('loot.items.notes.store');
    Route::put('/items/{item}/priorities', [PrioritiesController::class, 'update'])->can('update', 'item')->name('loot.items.priorities.update');
    Route::get('/items/{item}/edit', [ItemController::class, 'redirectToEdit'])->can('update', 'item');
    Route::get('/items/{item}/{name?}', [ItemController::class, 'view'])->name('loot.items.show');
    Route::get('/items/{item}/{name}/edit', [ItemController::class, 'edit'])->can('update', 'item')->name('loot.items.edit');

    // Comment routes
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('loot.comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('loot.comments.destroy');
    Route::post('/comments/{comment}/reactions', [CommentReactionController::class, 'store'])->name('loot.comments.reactions.store');
    Route::delete('/comments/{comment}/reactions/{reaction}', [CommentReactionController::class, 'destroy'])->name('loot.comments.reactions.destroy');
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
    Route::get('/manage-ranks', [GuildRankController::class, 'list'])->name('ranks.view');
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

Route::get('/info/battlenet-usage', function () {
    return Inertia::render('BattlenetUsage');
})->name('battlenet-usage');

Route::get('/info/privacy', function () {
    return Inertia::render('PrivacyPolicy');
})->name('privacypolicy');

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });

require __DIR__.'/auth.php';
