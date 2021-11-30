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

/*
 * - подтягиваются профили с Яндекс
 * - отправляются в амо
 * - отпправляются обновленные профили
 * - отправляются транзакции
 * - отправляются обновленные транзакции
 */
//подтягивает новые аккаунты в бд, 1
Route::get('profiles', [ ProfileController::class, 'profiles' ]);

//отрабатывает профили с амо, 20+
Route::post('send', [ ProfileController::class, 'send' ]);

//отрабатывает повторные обновления
//Route::post('profiles/updated', [ ProfileController::class, 'send_updated' ]);

//отрабатывает транзакции/сделки с амо, 20+
Route::post('transactions', [ TransactionController::class, 'transactions' ]);

//отрабатывает валидные повторные обновления профилей
//Route::post('updated', [ TransactionController::class, 'transactions_updated' ]);

//крон проверки отслеживаемых сделок на изменение этапа
//Route::post('check', [ TransactionController::class, 'check_status' ]);

//тестирование
Route::get('test', [ ProfileController::class, 'test' ]);
