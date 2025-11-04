<?php

namespace App\Services;

use App\Actions\Telegram\HandleCallbackQueryAction;
use App\Actions\Telegram\HandleClearCommandAction;
use App\Actions\Telegram\HandleStartCommandAction;
use App\Models\TelegramUser;
use App\Models\TelegramMessage;
use App\Services\AiChatService;
use Telegram\Bot\Api as TelegramApi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected TelegramApi $telegram;
    protected AiChatService $aiChat;
    protected int $historyLimit = 10; // сколько последних сообщений хранить для контекста


    public function __construct(TelegramApi $telegram, AiChatService $aiChat)
    {
        $this->telegram = $telegram;
        $this->aiChat = $aiChat;
    }

    /**
     * Обрабатывает входящее сообщение:
     */
    public function handleIncomingMessage(array $update): void
    {
        $chat = $update['message']['chat'] ?? null;
        $text = trim($update['message']['text'] ?? '');
       
        if (!$chat || !$text)
            return;
 
        // 1️⃣ Сохраняем пользователя
        $user = TelegramUser::updateOrCreate(
            ['chat_id' => $chat['id']],
            [
                'username' => $chat['username'] ?? null,
                'first_name' => $chat['first_name'] ?? null,
                'last_name' => $chat['last_name'] ?? null,
                'language_code' => $update['message']['from']['language_code'] ?? null,
                'last_active_at' => Carbon::now(),
            ]
        );
   
        if ($text === '/start') {
            app(HandleStartCommandAction::class)->execute($chat['id']);
            return;
        }

        if ($text === '/clear') {
            app(HandleClearCommandAction::class)->execute($chat['id'], $user->id);
            return;
        }

        // 3️⃣ Сохраняем входящее сообщение
        TelegramMessage::create([
            'user_id' => $user->id,
            'direction' => 'incoming',
            'message' => $text,
            'use_for_context' => true,
            'sent_at' => Carbon::now(),
        ]);

        // 4️⃣ Формируем историю контекста
        $messages = TelegramMessage::where('user_id', $user->id)
            ->where('use_for_context', true)
            ->orderBy('sent_at', 'asc')
            ->take($this->historyLimit * 2)
            ->get();

        $chatHistory = $messages->map(fn($msg) => [
            'role' => $msg->direction === 'incoming' ? 'user' : 'assistant',
            'content' => $msg->message
        ])->toArray();

        // системное сообщение
        if (!collect($chatHistory)->pluck('role')->contains('system')) {
            array_unshift($chatHistory, [
                'role' => 'system',
                'content' => 'Ты — умный ассистент для Telegram. Отвечай кратко, ясно и по делу.'
            ]);
        }

        // добавляем новое сообщение пользователя в конец
        $chatHistory[] = ['role' => 'user', 'content' => $text];

        // 5️⃣ Получаем ответ AI
        try {
            $answer = $this->aiChat->getResponseWithContext($chatHistory);
        } catch (\Throwable $e) {
            Log::error('AI response failed: ' . $e->getMessage());
            $answer = 'Извините, произошла ошибка при генерации ответа.';
        }

        // 6️⃣ Отправляем и сохраняем ответ
        $this->sendMessage($chat['id'], $answer);
        TelegramMessage::create([
            'user_id' => $user->id,
            'direction' => 'outgoing',
            'message' => $answer,
            'use_for_context' => true,
            'sent_at' => Carbon::now(),
        ]);
    }

    /**
     * Отправка сообщения с логированием
     */
    protected function sendMessage($chatId, $text): void
    {
        try {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $text
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to send message to chat_id {$chatId}: " . $e->getMessage());
        }
    }
}
