<?php

use App\Http\Controllers\Api\AttendanceNamesController;
use App\Http\Controllers\Api\BlizzardMediaController;
use App\Http\Controllers\Api\Discord\GuildResourceController;
use App\Http\Controllers\Api\Event\PublishAssignmentsController;
use App\Http\Controllers\Api\EventAssignmentController;
use App\Http\Controllers\Api\EventGroupController;
use App\Http\Controllers\Api\LootCouncil\CommentController;
use App\Http\Controllers\Api\SpellController;
use Illuminate\Support\Facades\Route;

Route::get('/attendance/names', AttendanceNamesController::class)
    ->middleware(['auth:sanctum', 'can:view-attendance'])
    ->name('api.attendance.names');

Route::middleware('auth:sanctum')->get('/blizzard/media', BlizzardMediaController::class)->name('api.blizzard.media');

Route::get('/discord/guild/members/search', [GuildResourceController::class, 'searchMembers'])
    ->name('api.discord.guild.members.search');

Route::post('/loot/comments/{comment}/resolve', [CommentController::class, 'resolve'])
    ->name('api.loot.comments.resolve');

Route::post('/spells', [SpellController::class, 'store'])->middleware(['auth:sanctum', 'can:create,App\Models\Spell'])->name('api.spells.store');

Route::middleware(['auth:sanctum', 'can:update,event'])->prefix('/events/{event}')->group(function () {
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
