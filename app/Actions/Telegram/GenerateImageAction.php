<?php

namespace App\Actions\Telegram;


use App\Services\Image\ImageGenerationService;
use Telegram\Bot\Api as TelegramApi;
use Illuminate\Support\Facades\Log;

class GenerateImageAction
{
    public function __construct(
        protected ImageGenerationService $imageService,
        protected TelegramApi $telegram,
    ) {}

    /**
     * Генерация изображения и отправка пользователю
     *
     * @param int $chatId
     * @param string $prompt
     */
    public function execute(int $chatId, string $prompt): void
    {
        try {
            $imageBase64 = $this->imageService->generateImage($prompt);

            if (!$imageBase64) {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Не удалось сгенерировать изображение. Попробуйте позже.',
                ]);
                return;
            }

            // Отправляем изображение как файл
            $tempFile = tempnam(sys_get_temp_dir(), 'hf_img_') . '.png';
            file_put_contents($tempFile, base64_decode(explode(',', $imageBase64)[1]));

            $this->telegram->sendPhoto([
                'chat_id' => $chatId,
                'photo' => fopen($tempFile, 'r'),
            ]);

            unlink($tempFile);

        } catch (\Throwable $e) {
            Log::error('GenerateImageAction error: ' . $e->getMessage());
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при генерации изображения.',
            ]);
        }
    }
}
