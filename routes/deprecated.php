<?php

use App\Http\Controllers\RaidingController;
use Illuminate\Support\Facades\Route;

// Old officers' dashboard route, now redirects to /manage
Route::get('/dashboard', fn () => redirect()->route('management.dashboard'));

// Previously used as a shortcut to the Google Docs sheet. Now redirects to the latest raid plans page.
Route::get('/comps', [RaidingController::class, 'comps'])->name('raiding.plans.next');

// Deprecated routes for managing addon councillors, now return 410 Gone
Route::post('/manage/addon/settings/councillors', fn () => abort(410));
Route::delete('/manage/addon/settings/councillors/{character}', fn () => abort(410));

// Old GRM upload form, now lives at /manage/grm
Route::get('/manage/grm-upload', fn () => redirect()->route('management.grm.create', [], 301));

// Old GRM upload submission, now return 410 Gone. A redirect would not help: the old body sent
// `game_version_id`, which the new `game_version` slug rule rejects.
Route::post('/manage/grm-upload', fn () => abort(410));
