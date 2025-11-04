<?php

namespace App\Actions\Telegram;

use App\Services\TelegramKeyboardService;
use Telegram\Bot\Api as TelegramApi;

class HandleStartCommandAction
{
    public function __construct(protected TelegramApi $telegram, protected TelegramKeyboardService $keyboardService) {}

    public function execute(int $chatId): void
    {
        $message = "👋 Привет! Я — чат-бот-ассистент для Telegram созданный на основе ИИ. 🤖\n\n" .
                   "Мои возможности:\n" .
                   "- Ответы на вопросы\n" .
                   "- Генерация текста\n" .
                   "- Перевод\n" .
                   "- Резюмирование\n" .
                   "- Помощь с кодом\n" .
                   "- Создание списков и таблиц\n" .
                   "- Подсчёты и простая математика\n" .
                   "- Советы и рекомендации\n\n" .
                   "⚠️ Ограничения: не имею доступа к интернету, не храню персональные данные.\n\n" .
                   "📋 Доступные команды:\n" .
                   "/start — список команд\n" .
                   "/generate_image — генерация изоюражений (в разработке)\n" .
                   "/clear — очистка контекста\n";

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            // 'reply_markup' => $this->keyboardService->mainMenu(),
        ]);
    }
}
