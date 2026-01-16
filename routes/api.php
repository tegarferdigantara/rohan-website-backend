<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Api\LauncherController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/register', [RegisteredUserController::class, 'store']);

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Launcher API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('launcher')->middleware('launcher.api')->group(function () {
    Route::post('/request-launch', [LauncherController::class, 'requestLaunch']);
    Route::post('/heartbeat', [LauncherController::class, 'heartbeat']);
    Route::post('/close-session', [LauncherController::class, 'closeSession']);
    Route::get('/status', [LauncherController::class, 'status']);
});

