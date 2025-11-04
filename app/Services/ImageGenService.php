<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageGenService
{
    protected string $apiKey;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.imagegen.api_key'); // положи API ключ в .env
        $this->apiUrl = 'https://api.deapi.ai/v1/images/generate'; // пример API
    }

    /**
     * Генерация изображения по описанию
     *
     * @param string $prompt Описание изображения
     * @return string URL изображения или сообщение об ошибке
     */
    public function generateImage(string $prompt): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'prompt' => $prompt,
                'size' => '1024x1024',
            ]);

            if (!$response->successful()) {
                Log::error('ImageGen API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return 'Не удалось сгенерировать изображение.';
            }

            $data = $response->json();
            return $data['url'] ?? 'Не удалось получить ссылку на изображение.';

        } catch (\Throwable $e) {
            Log::error('ImageGen Exception: ' . $e->getMessage());
            return 'Произошла ошибка при генерации изображения.';
        }
    }
}
