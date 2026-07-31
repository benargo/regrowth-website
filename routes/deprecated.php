<?php

use Illuminate\Support\Facades\Route;

Route::post('/addon/settings/councillors', fn () => abort(410));
Route::delete('/addon/settings/councillors/{character}', fn () => abort(410));
