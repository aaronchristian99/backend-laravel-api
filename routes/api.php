<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NewsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [LoginController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::post('/change-password', [LoginController::class, 'changeUserPassword']);
Route::post('/logout', [LoginController::class, 'logout']);
Route::get('/fetch-preference-list', [LoginController::class, 'fetchPreferencesList']);
Route::post('/store-user-preferences', [LoginController::class, 'storeUserPreferences']);
Route::get('/fetch-user-preferences', [LoginController::class, 'fetchUserPreferences']);
Route::post('/remove-user-preferences', [LoginController::class, 'RemoveUserPreferences']);
Route::get('/fetch-news', [NewsController::class, 'fetchNews']);
Route::post('/search-news', [NewsController::class, 'searchNews']);
Route::post('/filter-news', [NewsController::class, 'filterNews']);
