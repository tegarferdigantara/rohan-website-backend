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
Route::prefix('RohanAuth')->withoutMiddleware(['web'])->group(function () {
    // PHP endpoints (PascalCase)
    Route::match(['get', 'post'], '/Login3.php', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/Login3a.php', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/Login7.php', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/LoginRemove.php', [RohanAuthController::class, 'loginRemove']);
    Route::match(['get', 'post'], '/LoginRemoveUS.php', [RohanAuthController::class, 'loginRemove']);
    Route::match(['get', 'post'], '/SendCode3.php', [RohanAuthController::class, 'sendCode']);
    Route::match(['get', 'post'], '/SendCode7.php', [RohanAuthController::class, 'sendCode']);
    Route::match(['get', 'post'], '/ServerList5.php', [RohanAuthController::class, 'serverList']);
    Route::match(['get', 'post'], '/DownFlag2.php', [RohanAuthController::class, 'downFlag']);
    
    // ASP endpoints (PascalCase)
    Route::match(['get', 'post'], '/Login3.asp', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/Login3a.asp', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/Login7.asp', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/LoginRemove.asp', [RohanAuthController::class, 'loginRemove']);
    Route::match(['get', 'post'], '/LoginRemoveUS.asp', [RohanAuthController::class, 'loginRemove']);
    Route::match(['get', 'post'], '/SendCode3.asp', [RohanAuthController::class, 'sendCode']);
    Route::match(['get', 'post'], '/SendCode7.asp', [RohanAuthController::class, 'sendCode']);
    Route::match(['get', 'post'], '/ServerList5.asp', [RohanAuthController::class, 'serverList']);
    Route::match(['get', 'post'], '/DownFlag2.asp', [RohanAuthController::class, 'downFlag']);
    
    // Lowercase endpoints (some clients request lowercase)
    Route::match(['get', 'post'], '/login3.php', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/login3a.php', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/login7.php', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/loginremove.php', [RohanAuthController::class, 'loginRemove']);
    Route::match(['get', 'post'], '/loginremoveus.php', [RohanAuthController::class, 'loginRemove']);
    Route::match(['get', 'post'], '/sendcode3.php', [RohanAuthController::class, 'sendCode']);
    Route::match(['get', 'post'], '/sendcode7.php', [RohanAuthController::class, 'sendCode']);
    Route::match(['get', 'post'], '/serverlist5.php', [RohanAuthController::class, 'serverList']);
    Route::match(['get', 'post'], '/downflag2.php', [RohanAuthController::class, 'downFlag']);
    
    // Lowercase ASP endpoints
    Route::match(['get', 'post'], '/login3.asp', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/login3a.asp', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/login7.asp', [RohanAuthController::class, 'login']);
    Route::match(['get', 'post'], '/loginremove.asp', [RohanAuthController::class, 'loginRemove']);
    Route::match(['get', 'post'], '/loginremoveus.asp', [RohanAuthController::class, 'loginRemove']);
    Route::match(['get', 'post'], '/sendcode3.asp', [RohanAuthController::class, 'sendCode']);
    Route::match(['get', 'post'], '/sendcode7.asp', [RohanAuthController::class, 'sendCode']);
    Route::match(['get', 'post'], '/serverlist5.asp', [RohanAuthController::class, 'serverList']);
    Route::match(['get', 'post'], '/downflag2.asp', [RohanAuthController::class, 'downFlag']);
});

require __DIR__.'/auth.php';
