<?php

namespace App\Services\Image;

use App\Services\Image\Craiyon\CraiyonImageService;
use App\Services\Image\HuggingFace\HuggingFaceService;

use App\Services\Image\Pollinations\PollinationsImageService;
use App\Services\Image\Replicate\ReplicateService;
use Illuminate\Support\Facades\Log;

/**
 * Сервис генерации изображений через Hugging Face Router API (ожидание model_id в теле)
 */
class ImageGenerationService
{

    protected array $services;

    public function __construct(
        HuggingFaceService $hfService,
        ReplicateService $replicateService,
        CraiyonImageService $craiyonService,
        PollinationsImageService $pollinationsService
    ) {
        // порядок приоритета: HuggingFace -> Replicate -> Craiyon
        $this->services = [
            $hfService,
            $replicateService,
            $craiyonService,
            $pollinationsService
        ];
    }

    /**
     * Генерация изображения по текстовому описанию
     *
     * @param string $prompt
     * @param int $width
     * @param int $height
     * @return string|null Base64 изображения (ожидается JSON с base64 или бинарное изображение)
     */
    public function generateImage(string $prompt, int $width = 512, int $height = 512): ?string
    {
        foreach ($this->services as $service) {
            try {
                $image = $service->generateImage($prompt, $width, $height);
                if ($image) {
                    return $image;
                }
            } catch (\Throwable $e) {
                Log::warning(get_class($service) . " failed: " . $e->getMessage());
                // идём к следующему сервису
            }
        }

        Log::error("All image generation services failed for prompt: " . $prompt);
        return null;
    }
    public function saveImage(string $imageOrBase64, string $path): bool
    {
        try {
            if (str_starts_with($imageOrBase64, 'http')) {
                // Ссылка на изображение
                $image = file_get_contents($imageOrBase64);
                if (!$image)
                    return false;
            } else {
                // Base64
                $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageOrBase64), true);
                if ($image === false) {
                    Log::error('Base64 decode failed');
                    return false;
                }
            }

            file_put_contents($path, $image);
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to save image: ' . $e->getMessage());
            return false;
        }
    }

}