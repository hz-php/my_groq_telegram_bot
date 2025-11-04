<?php

namespace App\Actions\Telegram;

use App\Services\AiChatService;
use Telegram\Bot\Api as TelegramApi;
use Illuminate\Support\Facades\Log;

class HandleIncomingMessageAction
{
    public function __construct(
        protected AiChatService $ai,
        protected TelegramApi $telegram,
    ) {}

    public function execute(string $text, int $chatId): void
    {
        try {
            $response = $this->ai->getResponse($text);
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $response,
            ]);
        } catch (\Throwable $e) {
            Log::error('HandleIncomingMessageAction error: ' . $e->getMessage());
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка: ' . $e->getMessage(),
            ]);
        }
    }
}
