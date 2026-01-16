<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RohanAuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

/*
|--------------------------------------------------------------------------
| Legacy RohanAuth Routes (for RohanClient.exe compatibility)
|--------------------------------------------------------------------------
| Format: /RohanAuth/Login3.asp?nation=TN&id=xxx&passwd=xxx&...
*/
Route::prefix('RohanAuth')->withoutMiddleware(['web'])->group(function () {
    Route::match(['get', 'post'], '/Login3.asp', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/Login3a.asp', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/Login7.asp', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/LoginRemove.asp', [RohanAuthController::class, 'loginRemove']);
    Route::match(['get', 'post'], '/LoginRemoveUS.asp', [RohanAuthController::class, 'loginRemove']);
    Route::match(['get', 'post'], '/SendCode3.asp', [RohanAuthController::class, 'sendCode']);
    Route::match(['get', 'post'], '/SendCode7.asp', [RohanAuthController::class, 'sendCode']);
    Route::match(['get', 'post'], '/ServerList5.asp', [RohanAuthController::class, 'serverList']);
    Route::match(['get', 'post'], '/DownFlag2.asp', [RohanAuthController::class, 'downFlag']);
});

require __DIR__.'/auth.php';
