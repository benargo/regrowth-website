<?php

use App\Http\Controllers\Api\BlizzardMediaController;
use App\Http\Controllers\Api\Discord\GuildResourceController;
use App\Http\Controllers\Api\LootCouncil\CommentController;
use App\Http\Controllers\Api\SpellController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/blizzard/media', BlizzardMediaController::class)->name('api.blizzard.media');

Route::get('/discord/guild/members/search', [GuildResourceController::class, 'searchMembers'])
    ->name('api.discord.guild.members.search');

Route::post('/loot/comments/{comment}/resolve', [CommentController::class, 'resolve'])
    ->name('api.loot.comments.resolve');

Route::post('/spells', [SpellController::class, 'store'])->middleware(['auth:sanctum', 'can:create,App\Models\Spell'])->name('api.spells.store');
