<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SlotPlayer;
use Log;

class GameController extends Controller
{
    /**
     * Отображение игры
     */
    public function slot(Request $request)
    {
        $chatId = $request->query('chat_id', null);

        $player = SlotPlayer::firstOrCreate(
            ['telegram_chat_id' => $chatId],
            ['balance' => 1000, 'bonus' => 0, 'spins_count' => 0]
        );

        return view('game.slot', ['player' => $player]);
    }

    /**
     * Сохраняем результат спина
     */
    public function saveResult(Request $request)
    {
        $payload = $request->json()->all();

        $chatId = $payload['player'] ?? null;
        $linesWon = (int) ($payload['win'] ?? 0);
        $bet = (int) ($payload['bet'] ?? 10);

        if (!$chatId) return response()->json(['status' => 'error', 'message' => 'No player id']);

        $player = SlotPlayer::firstOrCreate(
            ['telegram_chat_id' => $chatId],
            ['balance' => 1000, 'bonus' => 0, 'spins_count' => 0]
        );

        // Обновляем баланс и счетчик спинов
        $player->balance -= $bet;
        $player->balance += $linesWon;
        $player->spins_count += 1;

        // Бонус каждые 10 спинов
        if ($player->spins_count % 10 === 0) {
            $player->bonus += 50;
            $player->balance += 50;
        }

        $player->save();

        return response()->json([
            'status' => 'ok',
            'balance' => $player->balance,
            'bonus' => $player->bonus
        ]);
    }
}
