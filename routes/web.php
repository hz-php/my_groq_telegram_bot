<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\TelegramWebhookController;
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

Route::get('/', function () {
    return view('welcome');
});

Route::post('/bot-webhook', [TelegramWebhookController::class, 'handleWebhook'])->name('telegram.webhook');

Route::get('/game/slot', [GameController::class, 'slot'])->name('game.slot');
Route::post('/game/slot/result', [GameController::class, 'saveResult'])->name('game.slot.result');