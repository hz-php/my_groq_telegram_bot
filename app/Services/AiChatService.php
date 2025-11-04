<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');
        $this->baseUrl = config('services.openrouter.base_url') . '/chat/completions';
        $this->model = config('services.openrouter.model');
    }

    /**
     * Отправляет запрос к AI с контекстом сообщений
     */
    public function getResponseWithContext(array $messages): string
    {
        try {
            $body = [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.3
            ];

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl, $body);

            if (!$response->successful()) {
                Log::error('OpenRouter request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                // Fallback на Groq
                return app(GroqService::class)->getResponseWithContext($messages);
            }

            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? 'Не удалось получить ответ.';

        } catch (\Throwable $e) {
            Log::error('OpenRouter request exception: ' . $e->getMessage());
            // Fallback на Groq
            return app(GroqService::class)->getResponseWithContext($messages);
        }
    }

    public function getResponse(string $text): string
    {
        // Простейший вариант: передаем только одно сообщение
        return $this->getResponseWithContext([
            ['role' => 'user', 'content' => $text]
        ]);
    }
}
