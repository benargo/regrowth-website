<?php

use App\Http\Controllers\Api\AttendanceNamesController;
use App\Http\Controllers\Api\BlizzardMediaController;
use App\Http\Controllers\Api\Discord\GuildResourceController;
use App\Http\Controllers\Api\Event\PublishAssignmentsController;
use App\Http\Controllers\Api\EventAssignmentController;
use App\Http\Controllers\Api\EventGroupController;
use App\Http\Controllers\Api\Loot\ResolveCommentController;
use App\Http\Controllers\Api\RaidHelper\DeleteEventController;
use App\Http\Controllers\Api\RaidHelper\SyncEventController;
use App\Http\Controllers\Api\RaidHelper\UpdateCompositionController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SpellController;
use Illuminate\Support\Facades\Route;

Route::get('/attendance/names', AttendanceNamesController::class)
    ->name('api.attendance.names');

Route::get('/blizzard/media', BlizzardMediaController::class)->name('api.blizzard.media');

Route::get('/discord/guild/members/search', [GuildResourceController::class, 'searchMembers'])
    ->name('api.discord.guild.members.search');

Route::get('/search', SearchController::class)->name('api.search');

Route::post('/loot/comments/{comment}/resolve', ResolveCommentController::class)
    ->name('api.loot.comments.resolve');

Route::post('/spells', [SpellController::class, 'store'])->name('api.spells.store');

Route::prefix('/raidhelper')->group(function () {
    Route::post('/event-create', SyncEventController::class);
    Route::post('/event-update', SyncEventController::class);
    Route::post('/event-delete', DeleteEventController::class);
    Route::post('/comp-update', UpdateCompositionController::class);
});

Route::middleware(['auth:sanctum'])->prefix('/events/{event}')->group(function () {
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
