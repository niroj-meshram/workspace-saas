<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This application is an API-only backend; the SPA lives in /frontend. These
| routes exist so the "web" middleware group (sessions, cookies, CSRF) stays
| registered, which Sanctum's SPA authentication relies on.
|
*/

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
]));
