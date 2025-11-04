<?php
// App/Actions/Telegram/HandleCallbackQueryAction.php

namespace App\Actions\Telegram;

use App\Services\TelegramKeyboardService;
use Telegram\Bot\Api as TelegramApi;

class HandleCallbackQueryAction
{
    public function __construct(
        protected TelegramApi $telegram,
        protected TelegramKeyboardService $keyboardService,
        protected HandleClearCommandAction $handleClearAction // Инжектим HandleClearCommandAction
    ) {}

    /**
     * @param array $callbackQuery Объект callback_query из обновления Telegram
     */
    public function execute(array $callbackQuery): void
    {
        $callbackData = $callbackQuery['data'] ?? null;
        $callbackId = $callbackQuery['id'];
        $chatId = $callbackQuery['message']['chat']['id'];
        $userId = $callbackQuery['from']['id']; // ID пользователя, который нажал кнопку

        if ($callbackData === '/clear') {
            // Вызываем HandleClearCommandAction, передав ему необходимые параметры
            $this->handleClearAction->execute($chatId, $userId);

            // Отвечаем на callback_query (важно!)
            // Учтите, что HandleClearCommandAction уже отправляет сообщение "Контекст переписки очищен."
            // Поэтому всплывающее сообщение в answerCallbackQuery может быть избыточным,
            // но оно подтверждает, что бот обработал нажатие.
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callbackId,
                // 'text' => 'Контекст очищен!', // Опционально: всплывающее сообщение (может дублировать сообщение в чате)
                'show_alert' => false,
            ]);

        } else {
            // Обработка других callback_data, если есть
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callbackId,
                'text' => 'Неизвестная команда.',
                'show_alert' => true,
            ]);
        }
    }
}