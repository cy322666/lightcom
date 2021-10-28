<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

//подтягивает новые аккаунты в бд, 1
Route::get('profiles', [ ProfileController::class, 'profiles' ]);

//отрабатывает профили с амо, 20+
Route::post('send', [ ProfileController::class, 'send' ]);

//отрабатывает транзакции/сделки с амо, 20+
Route::post('transactions', [ TransactionController::class, 'transactions' ]);
