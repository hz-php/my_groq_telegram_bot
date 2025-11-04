<?php

namespace App\Services;

use App\Actions\Telegram\HandleCallbackQueryAction;
use Telegram\Bot\Keyboard\Keyboard;
use App\Actions\Telegram\HandleClearCommandAction;
use App\Actions\Telegram\HandleStartCommandAction;
use App\Models\TelegramUser;
use App\Models\TelegramMessage;
use App\Services\AiChatService;
use App\Services\Image\ImageGenerationService;
use Telegram\Bot\Api as TelegramApi;
use Illuminate\Support\Carbon;
use Telegram\Bot\FileUpload\InputFile;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected TelegramApi $telegram;
    protected AiChatService $aiChat;
    protected ImageGenerationService $imageService;
    protected int $historyLimit = 10;

    public function __construct(TelegramApi $telegram, AiChatService $aiChat, ImageGenerationService $imageService)
    {
        $this->telegram = $telegram;
        $this->aiChat = $aiChat;
        $this->imageService = $imageService;
    }

    public function handleIncomingMessage(array $update): void
    {
        $chat = $update['message']['chat'] ?? null;
        $text = trim($update['message']['text'] ?? '');

        if (!$chat || $text === '') {
            return;
        }

        $user = $this->updateOrCreateUser($chat, $update);

        if ($this->handleCommands($user, $chat['id'], $text)) return;
        if ($this->handleMenuActions($user, $chat['id'], $text)) return;

        if ($user->mode === 'image_generation') {
            $this->handleImageGeneration($user, $chat['id'], $text);
            return;
        }

        if ($user->mode === 'audio_generation') {
            $this->handleAudioGeneration($user, $chat['id'], $text);
            return;
        }

        $this->handleAiChat($user, $chat['id'], $text);
    }

    protected function handleCommands(TelegramUser $user, int $chatId, string $text): bool
    {
        switch ($text) {
            case '/start':
                app(HandleStartCommandAction::class)->execute($chatId);
                $this->sendPersistentMenu($chatId, 'Добро пожаловать! Выберите действие:');
                return true;

            case '/clear':
                app(HandleClearCommandAction::class)->execute($chatId, $user->id);
                $this->sendPersistentMenu($chatId, 'История очищена.');
                return true;

            case '/generate_image':
                $user->mode = 'image_generation';
                $user->save();
                $this->sendPersistentMenu($chatId, 'Введите описание изображения:');
                return true;

            case 'Генерировать аудио':
                $user->mode = 'audio_generation';
                $user->save();
                $this->sendPersistentMenu($chatId, 'Введите текст для генерации аудио:');
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
                $this->sendPersistentMenu($chatId, 'Введите описание изображения:');
                return true;

            case 'Генерировать аудио':
                $user->mode = 'audio_generation';
                $user->save();
                $this->sendPersistentMenu($chatId, 'Введите текст для генерации аудио:');
                return true;

            case 'Помощь':
                $this->sendPersistentMenu($chatId, "Справка по боту:\n- /generate_image — сгенерировать картинку\n- /clear — очистить историю\n- Генерация аудио — через меню");
                return true;

            case 'Очистить историю':
                TelegramMessage::where('user_id', $user->id)
                    ->update(['use_for_context' => false]);
                $this->sendPersistentMenu($chatId, "Контекст беседы очищен.");
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

        $this->sendPersistentMenu($chatId, $answer);

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
            $this->sendPersistentMenu($chatId, "Не удалось сгенерировать аудио.");
        }

        $user->mode = null;
        $user->save();
    }

    protected function handleImageGeneration(TelegramUser $user, int $chatId, string $prompt): void
    {
        $imageDataUrl = $this->imageService->generateImage($prompt);

        if ($imageDataUrl) {
            $fileName = 'telegram_image_' . time() . '.png';
            $filePath = storage_path('app/public/' . $fileName);

            if ($this->imageService->saveImage($imageDataUrl, $filePath)) {
                $this->telegram->sendPhoto([
                    'chat_id' => $chatId,
                    'photo' => fopen($filePath, 'r'),
                    'caption' => 'Вот ваше изображение: ' . $prompt
                ]);
                $this->sendPersistentMenu($chatId, 'Что хотите сделать дальше?');
            } else {
                $this->sendPersistentMenu($chatId, "Не удалось сохранить изображение.");
            }
        } else {
            $this->sendPersistentMenu($chatId, "Не удалось сгенерировать изображение.");
        }

        $user->mode = null;
        $user->save();
    }

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

    protected function sendPersistentMenu(int $chatId, string $text): void
    {
        $keyboard = Keyboard::make([
            'keyboard' => [
                ['Генерировать изображение', 'Генерировать аудио'],
                ['Очистить историю', 'Помощь']
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ]);

        $safeText = trim($text) !== '' ? $text : ' '; // Неразрывный пробел, чтобы не пустая строка

        try {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $safeText,
                'reply_markup' => $keyboard,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to send persistent menu: " . $e->getMessage());
        }
    }
}
