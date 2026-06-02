<?php

use App\Http\Controllers\AttendanceDashboardController;
use App\Http\Controllers\AttendanceGraphsController;
use App\Http\Controllers\AttendanceMatrixController;
use App\Http\Controllers\BossStrategyController;
use App\Http\Controllers\DailyQuestsController;
use App\Http\Controllers\Dashboard\AddonController;
use App\Http\Controllers\Dashboard\AddonSchemaController;
use App\Http\Controllers\Dashboard\AddonSettingsController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\GrmController;
use App\Http\Controllers\Dashboard\GuildRankController;
use App\Http\Controllers\Dashboard\PermissionController;
use App\Http\Controllers\Dashboard\PhaseController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventTemplateController;
use App\Http\Controllers\GuildRosterController;
use App\Http\Controllers\Loot\CommentController;
use App\Http\Controllers\Loot\ItemController;
use App\Http\Controllers\Loot\LootController;
use App\Http\Controllers\Loot\ReactionController;
use App\Http\Controllers\Loot\ShowRaidController;
use App\Http\Controllers\PlannedAbsenceController;
use App\Http\Controllers\RaidingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServeIconController;
use App\Http\Controllers\WarcraftLogs\GuildTagController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');

/**
 * Icon serving
 */
Route::get('/icons/{size}/{name}', ServeIconController::class)
    ->name('icons.show')
    ->where('size', '[0-9]+')
    ->where('name', '[a-z0-9_]+\.(jpg|png)')
    ->middleware(['signed', 'throttle:icons']);

/**
 * Guild Roster
 */
Route::get('/roster', GuildRosterController::class)->name('roster');

/**
 * Loot Bias Tools
 */
Route::group(['prefix' => 'loot', 'as' => 'loot.', 'middleware' => ['auth']], function () {
    Route::get('/', [LootController::class, 'index'])->name('index');
    Route::get('/raids/{raid}/{name?}', ShowRaidController::class)->name('raids.show');
    Route::post('/items/{item}/comments', [CommentController::class, 'store'])->name('items.comments.store');
    Route::post('/items/{item}/notes', [ItemController::class, 'updateNotes'])->name('items.notes.store');
    Route::put('/items/{item}/priorities', [ItemController::class, 'updatePriorities'])->name('items.priorities.update');
    Route::get('/items/{item}/edit', [ItemController::class, 'redirectToEdit']);
    Route::get('/items/{item}/{name?}', [ItemController::class, 'show'])->name('items.show');
    Route::get('/items/{item}/{name}/edit', [ItemController::class, 'edit'])->name('items.edit');

    // Comment routes
    Route::get('/comments', [CommentController::class, 'index'])->name('comments.index');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('/comments/{comment}/reactions', [ReactionController::class, 'store'])->name('comments.reactions.store');
    Route::delete('/comments/{comment}/reactions/{reaction}', [ReactionController::class, 'destroy'])->name('comments.reactions.destroy');
});

/**
 * Raid planning and attendance
 */
Route::group(['prefix' => 'raiding', 'as' => 'raiding.'], function () {
    Route::get('/', [RaidingController::class, 'index'])->name('index');

    // Planned absences routes
    Route::get('/absences', [PlannedAbsenceController::class, 'index'])->name('absences.index');
    Route::get('/absences/create', [PlannedAbsenceController::class, 'create'])->name('absences.create');
    Route::post('/absences', [PlannedAbsenceController::class, 'store'])->name('absences.store');
    Route::get('/absences/{plannedAbsence}/edit', [PlannedAbsenceController::class, 'edit'])->name('absences.edit');
    Route::patch('/absences/{plannedAbsence}', [PlannedAbsenceController::class, 'update'])->name('absences.update');
    Route::delete('/absences/{plannedAbsence}', [PlannedAbsenceController::class, 'destroy'])->name('absences.destroy');
    Route::post('/absences/{plannedAbsence}/restore', [PlannedAbsenceController::class, 'restore'])->withTrashed()->name('absences.restore');

    // Attendance routes
    Route::get('/attendance', AttendanceDashboardController::class)->name('attendance.dashboard');
    Route::get('/attendance/graphs', [AttendanceGraphsController::class, 'index'])->name('attendance.graphs.index');
    Route::get('/attendance/matrix', AttendanceMatrixController::class)->name('attendance.matrix');

    // Upcoming events comps and plans routes
    Route::get('/plans/{event}', [EventController::class, 'show'])->name('plans.show');
    Route::get('/plans/{event}/edit', [EventController::class, 'edit'])->name('plans.edit');
    Route::post('/plans/{event}/apply-template', [EventController::class, 'applyTemplate'])->name('plans.apply-template');

    // Reports routes
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/create', [ReportController::class, 'create'])->name('reports.create');
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::patch('/reports/{report}', [ReportController::class, 'update'])->name('reports.update');
});

/**
 * Comps spreadsheet redirect
 */
Route::get('/comps', [RaidingController::class, 'comps'])->name('raiding.plans.next');

/*
 * Officers' Dashboard
 */
Route::group(['prefix' => 'dashboard', 'as' => 'dashboard.', 'middleware' => ['auth']], function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');

    /**
     * Addon management
     */
    Route::get('/addon/export', [AddonController::class, 'exportBase64'])->name('addon.export');
    Route::get('/addon/export/json', [AddonController::class, 'exportJson'])->name('addon.export.json');
    Route::get('/addon/export/schema', AddonSchemaController::class)->name('addon.export.schema');
    Route::get('/addon/settings', [AddonSettingsController::class, 'index'])->name('addon.settings');
    Route::post('/addon/settings/councillors', [AddonSettingsController::class, 'addCouncillor'])->name('addon.settings.councillors.add');
    Route::delete('/addon/settings/councillors/{character}', [AddonSettingsController::class, 'removeCouncillor'])->name('addon.settings.councillors.remove');

    /**
     * Boss strategies management
     */
    Route::get('/boss-strategies', [BossStrategyController::class, 'index'])->name('boss-strategies.index');
    Route::get('/boss-strategies/{boss}/{slug}', [BossStrategyController::class, 'edit'])->name('boss-strategies.edit');
    Route::patch('/boss-strategies/{boss}', [BossStrategyController::class, 'update'])->name('boss-strategies.update');

    /**
     * Daily Quests
     */
    Route::get('/daily-quests', [DailyQuestsController::class, 'form'])->name('daily-quests.form');
    Route::post('/daily-quests', [DailyQuestsController::class, 'store'])->name('daily-quests.store');
    Route::get('/daily-quests/audit', [DailyQuestsController::class, 'audit'])->name('daily-quests.audit');

    /**
     * Event templates
     */
    Route::get('/event-templates', [EventTemplateController::class, 'index'])->name('event-templates.index');
    Route::get('/event-templates/create', [EventTemplateController::class, 'create'])->name('event-templates.create');
    Route::post('/event-templates', [EventTemplateController::class, 'store'])->name('event-templates.store');
    Route::get('/event-templates/{template}/edit', [EventTemplateController::class, 'edit'])->name('event-templates.edit');
    Route::patch('/event-templates/{template}', [EventTemplateController::class, 'update'])->name('event-templates.update');
    Route::delete('/event-templates/{template}', [EventTemplateController::class, 'destroy'])->name('event-templates.destroy');

    /**
     * GRM data upload
     */
    Route::get('/grm-upload', [GrmController::class, 'showUploadForm'])->name('grm-upload.form');
    Route::post('/grm-upload', [GrmController::class, 'handleUpload'])->name('grm-upload.upload');

    /**
     * Phases management
     */
    Route::get('/phases', [PhaseController::class, 'index'])->name('phases.view');
    Route::put('/phases/{phase}', [PhaseController::class, 'update'])->name('phases.update');
    Route::put('/phases/{phase}/guild-tags', [PhaseController::class, 'updateGuildTags'])->name('phases.guild-tags.update');

    /**
     * Permissions management
     */
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::get('/permissions/{group}', [PermissionController::class, 'showGroup'])->name('permissions.group.show');
    Route::patch('/permissions/{group}/{permission}', [PermissionController::class, 'update'])->name('permissions.permission.update');

    /**
     * Ranks management
     */
    Route::get('/ranks', [GuildRankController::class, 'list'])->name('ranks.view');
    Route::post('/ranks/new', [GuildRankController::class, 'store'])->name('ranks.store');
    Route::post('/ranks/update-positions', [GuildRankController::class, 'updatePositions'])->name('ranks.update-positions');
    Route::put('/ranks/{guildRank}', [GuildRankController::class, 'update'])->name('ranks.update');
    Route::patch('/ranks/{guildRank}/count-attendance', [GuildRankController::class, 'toggleCountAttendance'])->name('ranks.toggle-attendance');
    Route::delete('/ranks/{guildRank}', [GuildRankController::class, 'destroy'])->name('ranks.destroy');
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
    ->name('wcl.guild-tags.toggle-attendance');

/**
 * Static information pages
 */
Route::get('/info/battlenet-usage', function () {
    return Inertia::render('Info/BattlenetUsage');
})->name('battlenet-usage');

Route::get('/info/privacy', function () {
    return Inertia::render('Info/PrivacyPolicy');
})->name('privacypolicy');

require __DIR__.'/auth.php';
