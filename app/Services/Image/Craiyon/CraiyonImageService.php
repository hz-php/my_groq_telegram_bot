<?php

namespace App\Services\Image\Craiyon;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CraiyonImageService
{
    /**
     * Генерирует изображение по текстовому описанию через неофициальный Craiyon API
     *
     * @param string $prompt
     * @return string|null URL изображения или null (если ошибка)
     */
    public function generateImage(string $prompt): ?string
    {
        try {
            // Пример эндпоинта (backend)
            $endpoint = 'https://backend.craiyon.com/generate';

            $response = Http::timeout(60)->post($endpoint, [
                'prompt' => $prompt
            ]);

            if (!$response->successful()) {
                // Log::error('Craiyon generate failed', [
                //     'status' => $response->status(),
                //     'body' => $response->body()
                // ]);
                return null;
            }

            $data = $response->json();

            // Ответ, вероятно, содержит поле "images" — массив URL-изображений
            if (isset($data['images']) && is_array($data['images']) && count($data['images']) > 0) {
                // Вернём первый URL
                return $data['images'][0];
            }

            return null;
        } catch (\Throwable $e) {
           // Log::error('Craiyon exception: ' . $e->getMessage());
            return null;
        }
    }
}
