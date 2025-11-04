<?php

namespace App\Services\Image\Replicate;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReplicateService
{
    protected string $apiKey;
    protected string $model = 'stability-ai/stable-diffusion-xl-base-1.0';

    public function __construct()
    {
        $this->apiKey = config('services.replicate.api_key');
    }

    public function generateImage(string $prompt, int $width = 1024, int $height = 1024): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)
              ->post('https://api.replicate.com/v1/predictions', [
                  'version' => $this->model,
                  'input' => ['prompt' => $prompt, 'width' => $width, 'height' => $height]
              ]);

            if (!$response->successful()) {
                // Log::warning('Replicate API failed', [
                //     'status' => $response->status(),
                //     'body' => $response->body(),
                // ]);
                return null;
            }

            $json = $response->json();

            if (isset($json['output'][0])) {
                $url = $json['output'][0];
                $imageData = file_get_contents($url);
                return 'image/png;base64,' . base64_encode($imageData);
            }

            return null;

        } catch (\Throwable $e) {
            //Log::error('ReplicateService exception: ' . $e->getMessage());
            return null;
        }
    }
}
