<?php

use App\Http\Controllers\RaidingController;
use App\Models\Boss;
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

// Guild tags no longer belong to phases. Their reports derive a phase from their guild and start time.
Route::put('/manage/phases/{phase}/guild-tags', fn () => abort(410));

// Old phase start date form, now part of the phase Edit page (PATCH /manage/phases/{phase}).
Route::put('/manage/phases/{phase}', fn () => abort(410));

// Old guild tag attendance toggle, now scoped to its guild under /manage/warcraftlogs/guilds.
Route::patch('/datasets/guild-tags/{guildTag}/count-attendance', fn () => abort(410));

// Daily quests were retired with the move to Forever, now return 410 Gone
Route::get('/daily-quests', fn () => abort(410));
Route::get('/manage/daily-quests', fn () => abort(410));
Route::post('/manage/daily-quests', fn () => abort(410));
Route::get('/manage/daily-quests/audit', fn () => abort(410));

// Old boss strategy pages, now the boss management pages under /manage/bosses.
Route::get('/manage/boss-strategies', fn () => redirect()->route('management.bosses.index', [], 301));
Route::get('/manage/boss-strategies/{boss}/{slug}', fn (Boss $boss) => redirect()->route('management.bosses.edit', $boss, 301));

// Old boss strategy update, now part of PATCH /manage/bosses/{boss}, which takes image ids rather than URLs. Returns 410 Gone.
Route::patch('/manage/boss-strategies/{boss}', fn () => abort(410));

// Old boss strategy member pages, now the raid index and each boss's Raiding page.
Route::get('/raiding/boss-strategies', fn () => redirect()->route('raiding.raids.index', status: 301));
Route::get('/raiding/boss-strategies/{boss}/{slug}', fn (Boss $boss) => redirect()->route('raiding.bosses.show', ['boss' => $boss, 'slug' => $boss->slug], 301));
