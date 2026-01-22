<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Rohan\RohanAuthController;

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
Route::prefix('RohanAuth')
    ->withoutMiddleware(['web'])
    ->middleware(['cf.debug', \App\Http\Middleware\DisableCloudflareCompression::class])
    ->group(function () {
    // ASP RohanAuth endpoints
    Route::match(['get', 'post'], '/Login3.asp', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/ServerList5.asp', [RohanAuthController::class, 'serverList']);
    Route::match(['get', 'post'], '/loginremove.asp', [RohanAuthController::class, 'loginRemove']);
    Route::match(['get', 'post'], '/sendcode7.asp', [RohanAuthController::class, 'sendCode']);
    Route::match(['get', 'post'], '/DownFlag2.asp', [RohanAuthController::class, 'downFlag']);    
    Route::match(['get', 'post'], '/downflag2.asp', [RohanAuthController::class, 'downFlag']);
});

require __DIR__.'/auth.php';
