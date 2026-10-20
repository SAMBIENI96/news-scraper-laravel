<?php

use App\Http\Controllers\NewsController;
use Illuminate\Support\Facades\Route;

// Backend search endpoint — returns filtered JSON
Route::get('/news', [NewsController::class, 'search'])->name('api.news.search');
