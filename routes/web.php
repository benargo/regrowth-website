<?php

use App\Enums\SiteSection;
use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\PlannedAbsenceController as AccountPlannedAbsenceController;
use App\Http\Controllers\AttendanceDashboardController;
use App\Http\Controllers\AttendanceGraphsController;
use App\Http\Controllers\AttendanceMatrixController;
use App\Http\Controllers\BossController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\Dashboard\AddonController;
use App\Http\Controllers\Dashboard\AddonSchemaController;
use App\Http\Controllers\Dashboard\AddonSettingsController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\PermissionController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventTemplateController;
use App\Http\Controllers\GameVersionController;
use App\Http\Controllers\GuildRosterManager\ImportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LootBiasToolController;
use App\Http\Controllers\PhaseController;
use App\Http\Controllers\PlannedAbsenceController;
use App\Http\Controllers\RaidController;
use App\Http\Controllers\RaidingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Session\GameVersionController as SessionGameVersionController;
use App\Http\Controllers\WarcraftLogs\GuildController;
use App\Http\Controllers\WarcraftLogs\GuildTagController;
use App\Http\Middleware\RemembersCurrentGameVersion;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

Route::get('/search', SearchController::class)->name('search');

Route::patch('/session/game-version', [SessionGameVersionController::class, 'update'])->name('session.game-version.update');

/**
 * User account settings
 */
Route::group(['prefix' => 'account', 'as' => 'account.', 'middleware' => ['auth']], function () {
    Route::get('/', AccountController::class)->name('index');

    // Self-service planned absences
    Route::get('/absences/create', [AccountPlannedAbsenceController::class, 'create'])->name('absences.create');
    Route::post('/absences', [AccountPlannedAbsenceController::class, 'store'])->name('absences.store');
    Route::get('/absences/{plannedAbsence}/edit', [AccountPlannedAbsenceController::class, 'edit'])->name('absences.edit');
    Route::patch('/absences/{plannedAbsence}', [AccountPlannedAbsenceController::class, 'update'])->name('absences.update');
    Route::delete('/absences/{plannedAbsence}', [AccountPlannedAbsenceController::class, 'destroy'])->name('absences.destroy');
});

/**
 * Guild Roster
 */
Route::get('/roster', [CharacterController::class, 'index'])
    ->name('roster.index');
Route::get('/roster/characters', fn () => redirect()->route('roster.index', status: 303));
Route::get('/roster/characters/{character}/{slug?}', [CharacterController::class, 'show'])
    ->name('characters.show');

/**
 * Character management
 */
Route::withoutMiddleware(RemembersCurrentGameVersion::class)->group(function () {
    Route::get('/manage/characters/{character}/{slug}/edit', [CharacterController::class, 'edit'])
        ->name('characters.edit');
    Route::patch('/manage/characters/{character}', [CharacterController::class, 'update'])
        ->name('characters.update');
});

/**
 * Register the raid and boss pages shared by a member section.
 */
$raidAndBossRoutes = function (SiteSection $section): void {
    Route::get('/raids', [RaidController::class, 'browse'])->defaults('section', $section->value)->name('raids.index');
    Route::get('/raids/{raid}/{slug}/trash', [RaidController::class, 'trash'])->defaults('section', $section->value)->name('raids.trash');
    Route::get('/raids/{raid}/{slug?}', [RaidController::class, 'show'])->defaults('section', $section->value)->name('raids.show');
    Route::get('/bosses/{boss}/{slug?}', [BossController::class, 'show'])->defaults('section', $section->value)->name('bosses.show');
};

/**
 * Loot Bias Tools
 */
Route::group(['prefix' => 'loot', 'as' => 'loot.'], function () use ($raidAndBossRoutes) {
    Route::get('/', [LootBiasToolController::class, 'index'])->name('index');
    $raidAndBossRoutes(SiteSection::Loot);
    Route::get('/comments', [CommentController::class, 'index'])->name('comments');
    Route::patch('/items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::get('/items/{item}/{slug?}', [ItemController::class, 'show'])->name('items.show');
    Route::get('/items/{item}/{slug}/edit', [ItemController::class, 'edit'])->name('items.edit');
    Route::get('/priorities', [LootBiasToolController::class, 'priorities'])->name('priorities');
});

/**
 * Raid planning and attendance
 */
Route::group(['prefix' => 'raiding', 'as' => 'raiding.'], function () use ($raidAndBossRoutes) {
    Route::get('/', [RaidingController::class, 'index'])->name('index');
    $raidAndBossRoutes(SiteSection::Raiding);

    // Planned absences routes (version-independent)
    Route::withoutMiddleware(RemembersCurrentGameVersion::class)->group(function () {
        Route::get('/absences', [PlannedAbsenceController::class, 'index'])->name('absences.index');
        Route::get('/absences/create', [PlannedAbsenceController::class, 'create'])->name('absences.create');
        Route::post('/absences', [PlannedAbsenceController::class, 'store'])->name('absences.store');
        Route::get('/absences/{plannedAbsence}/edit', [PlannedAbsenceController::class, 'edit'])->name('absences.edit');
        Route::patch('/absences/{plannedAbsence}', [PlannedAbsenceController::class, 'update'])->name('absences.update');
        Route::delete('/absences/{plannedAbsence}', [PlannedAbsenceController::class, 'destroy'])->name('absences.destroy');
        Route::post('/absences/{plannedAbsence}/restore', [PlannedAbsenceController::class, 'restore'])->withTrashed()->name('absences.restore');
    });

    // Attendance routes
    Route::get('/attendance', AttendanceDashboardController::class)->name('attendance.dashboard');
    Route::get('/attendance/graphs', AttendanceGraphsController::class)->name('attendance.graphs.index');
    Route::get('/attendance/matrix', AttendanceMatrixController::class)->name('attendance.matrix');

    // Upcoming events comps and plans routes
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
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

/*
 * Officers' Dashboard
 */
Route::group(['prefix' => 'manage', 'as' => 'management.', 'middleware' => ['auth'], 'excluded_middleware' => [RemembersCurrentGameVersion::class]], function () {
    Route::get('/', fn () => redirect()->route('management.dashboard'))->name('index');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    /**
     * Addon management
     */
    Route::get('/addon/export', [AddonController::class, 'exportBase64'])->name('addon.export');
    Route::get('/addon/export/json', [AddonController::class, 'exportJson'])->name('addon.export.json');
    Route::get('/addon/export/schema', AddonSchemaController::class)->name('addon.export.schema');
    Route::get('/addon/settings', AddonSettingsController::class)->name('addon.settings');

    /**
     * Bosses management
     */
    Route::get('/bosses', [BossController::class, 'index'])->name('bosses.index');
    Route::get('/bosses/create', [BossController::class, 'create'])->name('bosses.create');
    Route::post('/bosses', [BossController::class, 'store'])->name('bosses.store');
    Route::get('/bosses/{boss}/edit', [BossController::class, 'edit'])->name('bosses.edit');
    Route::patch('/bosses/{boss}', [BossController::class, 'update'])->name('bosses.update');
    Route::delete('/bosses/{boss}', [BossController::class, 'destroy'])->name('bosses.destroy');

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
     * Game versions management
     */
    Route::get('/game-versions', [GameVersionController::class, 'index'])->name('game-versions.index');
    Route::get('/game-versions/create', [GameVersionController::class, 'create'])->name('game-versions.create');
    Route::post('/game-versions', [GameVersionController::class, 'store'])->name('game-versions.store');
    Route::get('/game-versions/{gameVersion}/edit', [GameVersionController::class, 'edit'])->name('game-versions.edit');
    Route::get('/game-versions/{gameVersion}/setup/{step}', [GameVersionController::class, 'setup'])->name('game-versions.setup');
    Route::get('/game-versions/{gameVersion}/review', [GameVersionController::class, 'review'])->name('game-versions.review');
    Route::patch('/game-versions/{gameVersion}', [GameVersionController::class, 'update'])->name('game-versions.update');
    Route::delete('/game-versions/{gameVersion}', [GameVersionController::class, 'destroy'])->name('game-versions.destroy');

    /**
     * GRM data upload
     */
    Route::get('/grm', [ImportController::class, 'create'])->name('grm.create');
    Route::post('/grm', [ImportController::class, 'store'])->name('grm.store');

    /**
     * Phases management
     */
    Route::get('/phases', [PhaseController::class, 'index'])->name('phases.index');
    Route::get('/phases/create', [PhaseController::class, 'create'])->name('phases.create');
    Route::post('/phases', [PhaseController::class, 'store'])->name('phases.store');
    Route::patch('/phases/order', [PhaseController::class, 'reorder'])->name('phases.reorder');
    Route::get('/phases/{phase}/edit', [PhaseController::class, 'edit'])->name('phases.edit');
    Route::patch('/phases/{phase}', [PhaseController::class, 'update'])->name('phases.update');
    Route::delete('/phases/{phase}', [PhaseController::class, 'destroy'])->name('phases.destroy');

    /**
     * Permissions management
     */
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::get('/permissions/{group}', [PermissionController::class, 'showGroup'])->name('permissions.group.show');
    Route::patch('/permissions/{group}/{permission}', [PermissionController::class, 'update'])->name('permissions.permission.update');

    /**
     * Raids management
     */
    Route::get('/raids', [RaidController::class, 'index'])->name('raids.index');
    Route::get('/raids/create', [RaidController::class, 'create'])->name('raids.create');
    Route::post('/raids', [RaidController::class, 'store'])->name('raids.store');
    Route::get('/raids/{raid}/edit', [RaidController::class, 'edit'])->name('raids.edit');
    Route::get('/raids/{raid}/setup/{step}', [RaidController::class, 'setup'])->name('raids.setup');
    Route::get('/raids/{raid}/review', [RaidController::class, 'review'])->name('raids.review');
    Route::patch('/raids/{raid}', [RaidController::class, 'update'])->name('raids.update');
    Route::delete('/raids/{raid}', [RaidController::class, 'destroy'])->name('raids.destroy');

    /**
     * Warcraft Logs guilds management
     */
    Route::group(['prefix' => 'warcraftlogs/guilds', 'as' => 'warcraftlogs.guilds.'], function () {
        Route::get('/', [GuildController::class, 'index'])->name('index');
        Route::get('/create', [GuildController::class, 'create'])->name('create');
        Route::post('/', [GuildController::class, 'store'])->name('store');
        Route::get('/{guild}', [GuildController::class, 'show'])->name('show');
        Route::patch('/{guild}', [GuildController::class, 'update'])->name('update');
        Route::delete('/{guild}', [GuildController::class, 'destroy'])->name('destroy');
        Route::patch('/{guild}/tags/{guildTag}/count-attendance', [GuildTagController::class, 'toggleCountAttendance'])
            ->scopeBindings()
            ->name('tags.toggle-attendance');
    });
});

/**
 * Static information pages
 */
Route::get('/info/battlenet-usage', function () {
    return Inertia::render('Info/BattlenetUsage');
})->name('battlenet-usage');

Route::get('/info/privacy', function () {
    return Inertia::render('Info/PrivacyPolicy');
})->name('privacypolicy');

/**
 * Include additional route files
 */
require __DIR__.'/assets.php';
require __DIR__.'/auth.php';
require __DIR__.'/deprecated.php';
require __DIR__.'/testing.php';
