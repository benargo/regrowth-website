<?php

use App\Http\Controllers\Api\LootCouncil\CommentController;
use Illuminate\Support\Facades\Route;

Route::post('/loot/comments/{comment}/resolve', [CommentController::class, 'resolve'])
    ->name('api.loot.comments.resolve');
