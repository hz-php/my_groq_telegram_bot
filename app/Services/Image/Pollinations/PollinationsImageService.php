<?php

namespace App\Services\Image\Pollinations;

class PollinationsImageService
{
    protected string $model;
    protected string $apiToken;

    public function __construct(string $model = 'gptimage')
    {
        $this->model = $model;
        $this->apiToken = env('POLLINATIONS_API_TOKEN', '');
    }

    public function generateImage(string $prompt): ?string
    {
        // Формируем URL с выбранной моделью
        $url = 'https://image.pollinations.ai/prompt/'
            . urlencode($prompt)
            . '?model=' . $this->model . '&token=' . $this->apiToken;
            file_put_contents(__DIR__ . '/_DEBUG_', print_r($url, true));
        return $url; // Ссылка на сгенерированное изображение
    }

    public function saveImage(string $imageUrl, string $filePath): bool
    {
        $image = file_get_contents($imageUrl);
        if (!$image)
            return false;
        return file_put_contents($filePath, $image) !== false;
    }
}

