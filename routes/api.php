<?php

use App\Http\Controllers\Api\Discord\ResolveDiscordMemberController;
use App\Http\Controllers\Api\Event\PublishAssignmentsController;
use App\Http\Controllers\Api\EventAssignmentController;
use App\Http\Controllers\Api\EventGroupController;
use App\Http\Controllers\Api\GuildRosterManager\DesktopBroadcastAuthController;
use App\Http\Controllers\Api\GuildRosterManager\RosterStateController;
use App\Http\Controllers\Api\GuildRosterManager\SyncBatchController;
use App\Http\Controllers\Api\GuildRosterManager\SyncController;
use App\Http\Controllers\Api\GuildRosterManager\TargetController;
use App\Http\Controllers\Api\RaidHelper\DeleteEventController;
use App\Http\Controllers\Api\RaidHelper\SyncEventController;
use App\Http\Controllers\Api\RaidHelper\UpdateCompositionController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SpellController;
use App\Http\Middleware\AddServerTimeHeader;
use App\Http\Middleware\EnsureTransientToken;
use Illuminate\Support\Facades\Route;

Route::get('/search', SearchController::class)->name('api.search');

Route::post('/spells', [SpellController::class, 'store'])->name('api.spells.store');

Route::prefix('/raidhelper')->group(function () {
    Route::post('/event-create', SyncEventController::class);
    Route::post('/event-update', SyncEventController::class);
    Route::post('/event-delete', DeleteEventController::class);
    Route::post('/comp-update', UpdateCompositionController::class);
});

Route::middleware(['auth:api', EnsureTransientToken::class])->prefix('/discord')->group(function () {
    Route::post('/members/{member}/resolve', ResolveDiscordMemberController::class)
        ->whereNumber('member')
        ->name('api.discord.members.resolve');
});

Route::middleware(['auth:api', EnsureTransientToken::class])->prefix('/events/{event}')->group(function () {
    // reorder must be registered before {group}/{assignment} to avoid treating "reorder" as a model ID
    Route::patch('/groups/reorder', [EventGroupController::class, 'reorder'])->name('api.events.groups.reorder');
    Route::post('/groups', [EventGroupController::class, 'store'])->name('api.events.groups.store');
    Route::patch('/groups/{group}', [EventGroupController::class, 'update'])->name('api.events.groups.update');
    Route::delete('/groups/{group}', [EventGroupController::class, 'destroy'])->name('api.events.groups.destroy');

    Route::patch('/assignments/reorder', [EventAssignmentController::class, 'reorder'])->name('api.events.assignments.reorder');
    Route::post('/assignments', [EventAssignmentController::class, 'store'])->name('api.events.assignments.store');
    Route::patch('/assignments/{assignment}', [EventAssignmentController::class, 'update'])->name('api.events.assignments.update');
    Route::delete('/assignments/{assignment}', [EventAssignmentController::class, 'destroy'])->name('api.events.assignments.destroy');

    Route::post('/publish-assignments', PublishAssignmentsController::class)->name('api.events.publish-assignments');
});

Route::middleware(['auth:api', AddServerTimeHeader::class])->prefix('/grm')->group(function () {
    Route::post('/broadcasting/auth', DesktopBroadcastAuthController::class)->name('api.grm.broadcasting.auth');
    Route::get('/targets', TargetController::class)->name('api.grm.targets');
    Route::get('/rosters/{gameVersion:slug}/state', RosterStateController::class)->name('api.grm.rosters.state');
    Route::post('/syncs', [SyncController::class, 'store'])->name('api.grm.syncs.store');
    Route::post('/syncs/{sync}/members', [SyncBatchController::class, 'members'])->whereUuid('sync')->name('api.grm.syncs.members.store');
    Route::post('/syncs/{sync}/leavers', [SyncBatchController::class, 'leavers'])->whereUuid('sync')->name('api.grm.syncs.leavers.store');
    Route::post('/syncs/{sync}/alt-groups', [SyncBatchController::class, 'altGroups'])->whereUuid('sync')->name('api.grm.syncs.alt-groups.store');
    Route::post('/syncs/{sync}/bans', [SyncBatchController::class, 'bans'])->whereUuid('sync')->name('api.grm.syncs.bans.store');
    Route::post('/syncs/{sync}/commit', [SyncController::class, 'commit'])->whereUuid('sync')->name('api.grm.syncs.commit');
    Route::post('/syncs/{sync}/extend', [SyncController::class, 'extend'])->whereUuid('sync')->name('api.grm.syncs.extend');
    Route::delete('/syncs/{sync}', [SyncController::class, 'destroy'])->whereUuid('sync')->name('api.grm.syncs.destroy');
});
