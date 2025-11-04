<?php

namespace App\Actions\Telegram;

use App\Models\TelegramMessage;
use Telegram\Bot\Api as TelegramApi;

class HandleClearCommandAction
{
    public function __construct(protected TelegramApi $telegram) {}

    public function execute(int $chatId, int $userId): void
    {
        TelegramMessage::where('user_id', $userId)
            ->update(['use_for_context' => false]);

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Контекст переписки очищен. Начинаем новый чат."
        ]);
    }
}
