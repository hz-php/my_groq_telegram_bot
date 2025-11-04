<?php

namespace App\Services\Audio;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AudioGenerationService
{
    protected string $apiToken;

    public function __construct()
    {
        $this->apiToken = env('POLLINATIONS_API_TOKEN', ''); // можно прописать токен напрямую
    }

    /**
     * Генерация аудио из текста
     *
     * @param string $text
     * @param string $voice
     * @return string|null URL аудио
     */
    public function generateAudio(string $text, string $voice = 'allison'): ?string
    {
        if (empty($this->apiToken)) {
            Log::error("Pollinations API token is not set");
            return null;
        }

        $url = "https://text.pollinations.ai/" . urlencode($text) .
               "?model=openai-audio&voice={$voice}&token={$this->apiToken}";
      
        try {
            // Проверяем доступность URL
            $response = Http::get($url);
            file_put_contents(__DIR__ . '/_DEBUG_RES', print_r($response, true));
            if ($response->successful()) {
                // Pollinations отдаёт прямую ссылку на аудио
                return $url;
            } else {
                Log::error("Pollinations API error: " . $response->body());
                return null;
            }
        } catch (\Throwable $e) {
            Log::error("Audio generation failed: " . $e->getMessage());
            return null;
        }
    }
}
