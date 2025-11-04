<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected string $defaultSystemMessage = 'Ты — помощник для Telegram. Отвечай кратко и по делу.';

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
        $this->apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
    }

    /**
     * Отправляет запрос к модели Groq с контекстом сообщений
     *
     * @param array $messages Массив сообщений в формате ['role'=>'user|assistant|system', 'content'=>...]
     * @return string
     */
    public function getResponseWithContext(array $messages): string
    {
        // Если нет системного сообщения, добавляем по умолчанию
        if (!collect($messages)->pluck('role')->contains('system')) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $this->defaultSystemMessage
            ]);
        }

        $body = [
            'model' => 'compound-beta-mini', // можно настроить в config
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 512,
        ];

        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, $body);

            if (!$response->successful()) {
                Log::error('Groq API also failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return 'Извините, AI временно недоступен.';
            }

            $data = $response->json();

            return $data['choices'][0]['message']['content'] ?? 'Не удалось получить ответ от Groq.';

        } catch (\Throwable $e) {
            Log::error('Groq API Exception: ' . $e->getMessage());
            return 'Извините, произошла ошибка при обработке вашего запроса.';
        }
    }
}
