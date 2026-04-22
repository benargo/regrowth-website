<?php

use App\Http\Controllers\DailyQuestsController;
use App\Http\Controllers\Dashboard\AddonController;
use App\Http\Controllers\Dashboard\AddonSettingsController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\GrmController;
use App\Http\Controllers\Dashboard\GuildRankController;
use App\Http\Controllers\Dashboard\PermissionController;
use App\Http\Controllers\Dashboard\PhaseController;
use App\Http\Controllers\GuildRosterController;
use App\Http\Controllers\LootCouncil\BiasToolController;
use App\Http\Controllers\LootCouncil\CommentController;
use App\Http\Controllers\LootCouncil\CommentReactionController;
use App\Http\Controllers\LootCouncil\ItemController;
use App\Http\Controllers\LootCouncil\NotesController;
use App\Http\Controllers\LootCouncil\PrioritiesController;
use App\Http\Controllers\LootCouncil\RaidController;
use App\Http\Controllers\PlannedAbsenceController;
use App\Http\Controllers\Raid\AttendanceController;
use App\Http\Controllers\Raid\AttendanceGraphsController;
use App\Http\Controllers\Raid\AttendanceMatrixController;
use App\Http\Controllers\Raid\ReportController;
use App\Http\Controllers\WarcraftLogs\GuildTagController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');

/**
 * Guild Roster
 */
Route::get('/roster', [GuildRosterController::class, 'index'])->name('roster.index');

/**
 * Loot Bias Tools
 */
Route::group(['prefix' => 'loot', 'as' => 'loot.', 'middleware' => ['auth']], function () {
    Route::get('/', [BiasToolController::class, 'index'])->can('viewAny', 'App\Models\LootCouncil\Item')->name('index');
    Route::get('/phases/phase-{phase}', [BiasToolController::class, 'phase'])->can('viewAny', 'App\Models\LootCouncil\Item')->name('phase');
    Route::get('/raids/{raid}/{name?}', [RaidController::class, 'show'])->can('viewAny', 'App\Models\LootCouncil\Item')->name('raids.show');
    Route::post('/items/{item}/comments', [CommentController::class, 'store'])->can('create', 'App\Models\LootCouncil\Comment')->name('items.comments.store');
    Route::post('/items/{item}/notes', [NotesController::class, 'update'])->can('update', 'item')->name('items.notes.store');
    Route::put('/items/{item}/priorities', [PrioritiesController::class, 'update'])->can('update', 'item')->name('items.priorities.update');
    Route::get('/items/{item}/edit', [ItemController::class, 'redirectToEdit'])->can('update', 'item');
    Route::get('/items/{item}/{name?}', [ItemController::class, 'view'])->can('view', 'item')->name('items.show');
    Route::get('/items/{item}/{name}/edit', [ItemController::class, 'edit'])->can('update', 'item')->name('items.edit');

    // Comment routes
    Route::get('/comments', [CommentController::class, 'index'])->can('viewAny', 'App\Models\LootCouncil\Comment')->name('comments.index');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->can('update', 'comment')->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->can('delete', 'comment')->name('comments.destroy');
    Route::post('/comments/{comment}/reactions', [CommentReactionController::class, 'store'])->can('react', 'comment')->name('comments.reactions.store');
    Route::delete('/comments/{comment}/reactions/{reaction}', [CommentReactionController::class, 'destroy'])->can('react', 'comment')->name('comments.reactions.destroy');
});

/**
 * Raid planning and attendance
 */
Route::group(['prefix' => 'raids', 'as' => 'raids.', 'middleware' => ['auth']], function () {
    // Planned absences routes
    Route::get('/absences', [PlannedAbsenceController::class, 'index'])->can('viewAny', 'App\Models\PlannedAbsence')->name('absences.index');
    Route::get('/absences/create', [PlannedAbsenceController::class, 'create'])->can('create', 'App\Models\PlannedAbsence')->name('absences.create');
    Route::post('/absences', [PlannedAbsenceController::class, 'store'])->can('create', 'App\Models\PlannedAbsence')->name('absences.store');
    Route::get('/absences/{plannedAbsence}/edit', [PlannedAbsenceController::class, 'edit'])->can('update', 'plannedAbsence')->name('absences.edit');
    Route::patch('/absences/{plannedAbsence}', [PlannedAbsenceController::class, 'update'])->can('update', 'plannedAbsence')->name('absences.update');
    Route::delete('/absences/{plannedAbsence}', [PlannedAbsenceController::class, 'destroy'])->can('delete', 'plannedAbsence')->name('absences.destroy');
    Route::post('/absences/{plannedAbsence}/restore', [PlannedAbsenceController::class, 'restore'])->withTrashed()->can('restore', 'plannedAbsence')->name('absences.restore');
    // Route::post('/absences/{id}/restore', [PlannedAbsenceController::class, 'restore'])->can('restore', 'App\Models\PlannedAbsence')->name('absences.restore');

    // Attendance routes
    Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('can:view-attendance')->name('attendance.index');
    Route::get('/attendance/graphs', [AttendanceGraphsController::class, 'index'])->middleware('can:view-attendance')->name('attendance.graphs.index');
    Route::get('/attendance/matrix', [AttendanceMatrixController::class, 'matrix'])->middleware('can:view-attendance')->name('attendance.matrix');

    // Reports routes
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/create', [ReportController::class, 'create'])->can('create', 'App\Models\Raids\Report')->name('reports.create');
    Route::post('/reports', [ReportController::class, 'store'])->can('create', 'App\Models\Raids\Report')->name('reports.store');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->can('view', 'report')->name('reports.show');
    Route::patch('/reports/{report}', [ReportController::class, 'update'])->can('update', 'report')->name('reports.update');
});

/*
 * Officers' Dashboard
 */
Route::group(['prefix' => 'dashboard', 'as' => 'dashboard.', 'middleware' => ['auth', 'can:view-officer-dashboard']], function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');

    /**
     * Addon management
     */
    Route::get('/addon/export', [AddonController::class, 'export'])->name('addon.export');
    Route::get('/addon/export/json', [AddonController::class, 'exportJson'])->name('addon.export.json');
    Route::get('/addon/export/schema', [AddonController::class, 'exportSchema'])->name('addon.export.schema');
    Route::get('/addon/settings', [AddonSettingsController::class, 'index'])->name('addon.settings');
    Route::post('/addon/settings/councillors', [AddonSettingsController::class, 'addCouncillor'])->name('addon.settings.councillors.add');
    Route::delete('/addon/settings/councillors/{character}', [AddonSettingsController::class, 'removeCouncillor'])->name('addon.settings.councillors.remove');

    /**
     * Guild ranks management
     */
    Route::get('/ranks', [GuildRankController::class, 'list'])->name('ranks.view');
    Route::post('/ranks/new', [GuildRankController::class, 'store'])->name('ranks.store');
    Route::post('/ranks/update-positions', [GuildRankController::class, 'updatePositions'])->name('ranks.update-positions');
    Route::put('/ranks/{guildRank}', [GuildRankController::class, 'update'])->name('ranks.update');
    Route::patch('/ranks/{guildRank}/count-attendance', [GuildRankController::class, 'toggleCountAttendance'])->name('ranks.toggle-attendance');
    Route::delete('/ranks/{guildRank}', [GuildRankController::class, 'destroy'])->name('ranks.destroy');

    /**
     * Phases management
     */
    Route::get('/phases', [PhaseController::class, 'index'])->name('phases.view');
    Route::put('/phases/{phase}', [PhaseController::class, 'update'])->name('phases.update');
    Route::put('/phases/{phase}/guild-tags', [PhaseController::class, 'updateGuildTags'])->name('phases.guild-tags.update');

    /**
     * GRM data upload
     */
    Route::get('/grm-upload', [GrmController::class, 'showUploadForm'])->name('grm-upload.form');
    Route::post('/grm-upload', [GrmController::class, 'handleUpload'])->name('grm-upload.upload');
    Route::get('/grm-upload/status', [GrmController::class, 'getUploadStatus'])->name('grm-upload.status');

    /**
     * Daily Quests
     */
    Route::get('/daily-quests', [DailyQuestsController::class, 'form'])->name('daily-quests.form');
    Route::post('/daily-quests', [DailyQuestsController::class, 'store'])->name('daily-quests.store');
    Route::get('/daily-quests/audit', [DailyQuestsController::class, 'audit'])
        ->can('audit', 'App\Models\TBC\DailyQuestNotification')
        ->name('daily-quests.audit');

    /**
     * Permissions management
     */
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::get('/permissions/{group}', [PermissionController::class, 'showGroup'])->name('permissions.group.show');
    Route::patch('/permissions/{group}/{permission}', [PermissionController::class, 'update'])->name('permissions.permission.update');
});

/**
 * Daily quests
 */
Route::group(['prefix' => 'daily-quests', 'as' => 'daily-quests.'], function () {
    Route::get('/', [DailyQuestsController::class, 'index'])->name('index');
    // Route::get('/edit', [DailyQuestsController::class, 'form'])->name('form')->middleware('auth');
    // Route::post('/store', [DailyQuestsController::class, 'store'])->name('store')->middleware('auth');
});

/**
 * Warcraft Logs Guild Tags Management
 */
Route::patch('/datasets/guild-tags/{guildTag}/count-attendance', [GuildTagController::class, 'toggleCountAttendance'])
    ->middleware('auth')
    ->name('wcl.guild-tags.toggle-attendance');

/**
 * Comps spreadsheet redirect
 */
Route::get('/comps', function () {
    return redirect('https://docs.google.com/spreadsheets/d/1SYaMOFDtXxdRm7gQz6nG7c_B-N7rsf7P7QIRipkJkwg/view?pli=1&gid=934701754#gid=934701754', 303);
});

/**
 * Static infoformation pages
 */
Route::get('/info/battlenet-usage', function () {
    return Inertia::render('BattlenetUsage');
})->name('battlenet-usage');

Route::get('/info/privacy', function () {
    return Inertia::render('PrivacyPolicy');
})->name('privacypolicy');

require __DIR__.'/auth.php';
