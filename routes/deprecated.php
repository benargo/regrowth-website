<?php

use Illuminate\Support\Facades\Route;

Route::post('/manage/addon/settings/councillors', fn () => abort(410));
Route::delete('/manage/addon/settings/councillors/{character}', fn () => abort(410));
