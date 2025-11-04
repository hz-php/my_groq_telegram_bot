<?php

namespace App\Services\Image\HuggingFace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HuggingFaceService
{
    protected string $apiUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->apiUrl = 'https://api-inference.huggingface.co/models/stabilityai/stable-diffusion-xl-base-1.0';
        $this->apiKey = config('services.huggingface.api_key');
    }

    public function generateImage(string $prompt, int $width = 1024, int $height = 1024): ?string
    {
        try {
            $payload = [
                'inputs' => $prompt,
                'parameters' => [
                    'width' => $width,
                    'height' => $height
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)
              ->post($this->apiUrl, $payload);

            if (!$response->successful()) {
                // Log::warning('HuggingFace API failed', [
                //     'status' => $response->status(),
                //     'body' => $response->body(),
                // ]);
                return null;
            }

            $contentType = $response->header('Content-Type');
            $body = $response->body();

            if (str_starts_with($contentType, 'image/')) {
                return $contentType . ';base64,' . base64_encode($body);
            }

            // Если ответ JSON с base64
            $json = $response->json();
            if (isset($json[0]['image_base64'])) {
                return 'image/png;base64,' . $json[0]['image_base64'];
            }

            Log::warning('HuggingFace unexpected response', ['data' => $json]);
            return null;

        } catch (\Throwable $e) {
           // Log::error('HuggingFaceService exception: ' . $e->getMessage());
            return null;
        }
    }
}
