<?php

namespace App\Services;

use App\Actions\Telegram\HandleCallbackQueryAction;
use Telegram\Bot\Keyboard\Keyboard;
use App\Actions\Telegram\HandleClearCommandAction;
use App\Actions\Telegram\HandleStartCommandAction;
use App\Models\TelegramUser;
use App\Models\TelegramMessage;
use App\Services\AiChatService;
use App\Services\Image\ImageGenerationService; // Используем HuggingFace
use Telegram\Bot\Api as TelegramApi;
use Illuminate\Support\Carbon;
use Telegram\Bot\FileUpload\InputFile;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected TelegramApi $telegram;
    protected AiChatService $aiChat;
    protected ImageGenerationService $imageService; // сервис генерации изображений
    protected int $historyLimit = 10; // сколько последних сообщений хранить для контекста

    public function __construct(TelegramApi $telegram, AiChatService $aiChat, ImageGenerationService $imageService)
    {
        $this->telegram = $telegram;
        $this->aiChat = $aiChat;
        $this->imageService = $imageService;
    }

    /**
     * Обрабатывает входящее сообщение
     */
    public function handleIncomingMessage(array $update): void
    { file_put_contents(__DIR__ . '/_DEBUG_', print_r($update, true));
        $chat = $update['message']['chat'] ?? null;
        $text = trim($update['message']['text'] ?? '');
        if (!$chat || !$text)
            return;

        $user = $this->updateOrCreateUser($chat, $update);

        // Сначала проверяем системные команды
        if ($this->handleCommands($user, $chat['id'], $text))
            return;

        // Проверка кнопок меню
        if ($this->handleMenuActions($user, $chat['id'], $text))
            return;

        // Проверка режима генерации изображения
        if ($user->mode === 'image_generation') {
            $this->handleImageGeneration($user, $chat['id'], $text);
            return;
        }

        if ($user->mode === 'audio_generation') {
            $this->handleAudioGeneration($user, $chat['id'], $text);
            return;
        }
        // Все остальное: AI чат
        $this->handleAiChat($user, $chat['id'], $text);
    }

    protected function handleCommands(TelegramUser $user, int $chatId, string $text): bool
    {
        switch ($text) {
            case '/start':
                app(HandleStartCommandAction::class)->execute($chatId);
                $this->sendPersistentMenu($chatId);
                return true;

            case '/clear':
                app(HandleClearCommandAction::class)->execute($chatId, $user->id);
                $this->sendMessage($chatId, "История очищена");
                $this->sendPersistentMenu($chatId);
                return true;

            case '/generate_image':
                $user->mode = 'image_generation';
                $user->save();
                $this->sendPersistentMenu($chatId);
                $this->sendMessage($chatId, "Введите описание изображения:");
                return true;
            case 'Генерировать аудио':
                $user->mode = 'audio_generation';
                $user->save();
                $this->sendPersistentMenu($chatId);
                $this->sendMessage($chatId, "Введите что вы хотите послушать:");
                return true;
        }

        return false;
    }

    protected function handleMenuActions(TelegramUser $user, int $chatId, string $text): bool
    {
        switch ($text) {
            case 'Генерировать изображение':
                $user->mode = 'image_generation';
                $user->save();
                $this->sendMessage($chatId, "Введите описание изображения:");
                return true;
            case 'Генерировать аудио':
                $user->mode = 'audio_generation';
                $user->save();
                $this->sendMessage($chatId, "Введите текст для генерации аудио:");
                return true;
            case 'Помощь':
                $this->sendMessage($chatId, "Справка по боту:\n- Генерировать изображение /generate_image \n- Очистить историю /clear\n- Генерировать аудио");
                return true;

            case 'Очистить историю':
                TelegramMessage::where('user_id', $user->id)->delete();
                $this->sendMessage($chatId, "История сообщений очищена.");
                return true;
        }

        return false;
    }

    protected function handleAiChat(TelegramUser $user, int $chatId, string $text): void
    {
        TelegramMessage::create([
            'user_id' => $user->id,
            'direction' => 'incoming',
            'message' => $text,
            'use_for_context' => true,
            'sent_at' => Carbon::now(),
        ]);

        $messages = TelegramMessage::where('user_id', $user->id)
            ->where('use_for_context', true)
            ->orderBy('sent_at', 'asc')
            ->take($this->historyLimit * 2)
            ->get();

        $chatHistory = $messages->map(fn($msg) => [
            'role' => $msg->direction === 'incoming' ? 'user' : 'assistant',
            'content' => $msg->message
        ])->toArray();

        if (!collect($chatHistory)->pluck('role')->contains('system')) {
            array_unshift($chatHistory, [
                'role' => 'system',
                'content' => 'Ты — умный ассистент для Telegram. Отвечай кратко, ясно и по делу.'
            ]);
        }

        $chatHistory[] = ['role' => 'user', 'content' => $text];

        try {
            $answer = $this->aiChat->getResponseWithContext($chatHistory);
        } catch (\Throwable $e) {
            Log::error('AI response failed: ' . $e->getMessage());
            $answer = 'Извините, произошла ошибка при генерации ответа.';
        }

        $this->sendMessage($chatId, $answer);
        $this->sendPersistentMenu($chatId);

        TelegramMessage::create([
            'user_id' => $user->id,
            'direction' => 'outgoing',
            'message' => $answer,
            'use_for_context' => true,
            'sent_at' => Carbon::now(),
        ]);
    }


    protected function updateOrCreateUser(array $chat, array $update): TelegramUser
    {
        return TelegramUser::updateOrCreate(
            ['chat_id' => $chat['id']],
            [
                'username' => $chat['username'] ?? null,
                'first_name' => $chat['first_name'] ?? null,
                'last_name' => $chat['last_name'] ?? null,
                'language_code' => $update['message']['from']['language_code'] ?? null,
                'last_active_at' => Carbon::now(),
            ]
        );
    }

    protected function handleAudioGeneration(TelegramUser $user, int $chatId, string $text): void
    {
        $audioUrl = app(\App\Services\Audio\AudioGenerationService::class)
            ->generateAudio($text, 'fable');

        if ($audioUrl) {
            $this->telegram->sendAudio([
                'chat_id' => $chatId,
                'audio' => InputFile::create($audioUrl, 'audio.mp3'),
                'caption' => 'Вот ваше аудио'
            ]);
        } else {
            $this->sendMessage($chatId, "Не удалось сгенерировать аудио.");
        }

        $user->mode = null;
        $user->save();
        $this->sendPersistentMenu($chatId);
    }

    /**
     * Обработка генерации изображения
     */
    protected function handleImageGeneration(TelegramUser $user, int $chatId, string $prompt): void
    {
        $imageDataUrl = $this->imageService->generateImage($prompt);

        if ($imageDataUrl) {
            $fileName = 'telegram_image_' . time() . '.png';
            $filePath = storage_path('app/public/' . $fileName);

            // Сохраняем изображение
            if ($this->imageService->saveImage($imageDataUrl, $filePath)) {
                // Отправляем файл Telegram
                $this->telegram->sendPhoto([
                    'chat_id' => $chatId,
                    'photo' => fopen($filePath, 'r'),
                    'caption' => 'Вот ваше изображение: ' . $prompt
                ]);
                $this->sendPersistentMenu($chatId);
            } else {
                $this->sendMessage($chatId, "Не удалось сохранить изображение.");
                $this->sendPersistentMenu($chatId);
            }
        } else {
            $this->sendMessage($chatId, "Не удалось сгенерировать изображение.");
            $this->sendPersistentMenu($chatId);
        }

        // Сброс режима
        $user->mode = null;
        $user->save();
    }

    /**
     * Отправка текстового сообщения с логированием
     */
    protected function sendMessage(int $chatId, string $text): void
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

    protected function sendPersistentMenu(int $chatId): void
    {
        $keyboard = Keyboard::make([
            'keyboard' => [
                ['Генерировать изображение', 'Генерировать аудио'],
                ['Очистить историю', 'Помощь']
            ],
            'resize_keyboard' => true,   // подгоняем размер кнопок под экран
            'one_time_keyboard' => false // кнопки остаются на экране
        ]);

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Выберите действие:',
            'reply_markup' => $keyboard
        ]);
    }
}
