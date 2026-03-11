<?php

use App\Http\Controllers\Api\Discord\GuildResourceController;
use App\Http\Controllers\Api\LootCouncil\CommentController;
use Illuminate\Support\Facades\Route;

Route::post('/loot/comments/{comment}/resolve', [CommentController::class, 'resolve'])
    ->name('api.loot.comments.resolve');

Route::get('/discord/guild/members/search', [GuildResourceController::class, 'searchMembers'])
    ->name('api.discord.guild.members.search');
