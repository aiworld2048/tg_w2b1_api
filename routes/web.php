<?php

use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\PlayerController;
use App\Http\Controllers\TransferLogController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

require_once __DIR__.'/admin.php';

Route::get('/', [HomeController::class, 'index'])->name('home');

// Authentication routes
Route::get('/login', [LoginController::class, 'showLogin'])
    ->name('login')
    ->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])
    ->name('login.attempt')
    ->middleware('guest');
Route::post('logout', [LoginController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');
Route::post('update-password/{user}', [LoginController::class, 'updatePassword'])
    ->name('updatePassword')
    ->middleware('auth');



Route::get('admin/product/game-list', [\App\Http\Controllers\Admin\ProductController::class, 'GameListFetch'])->name('admin.product.game-list');

