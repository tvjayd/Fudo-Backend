<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class MealImageService
{
    protected string $provider;
    protected bool $usePlaceholderFallback;

    public function __construct()
    {
        $this->provider = config('image_generation.provider', 'openai');
        $this->usePlaceholderFallback = config('image_generation.use_placeholder_fallback', true);
    }

    /**
     * Generate a real meal image using AI image generation.
     *
     * @param string $dishName
     * @param string|null $description
     * @param string $mealType
     * @return string Image URL
     */
    public function generateMealImageUrl(string $dishName, ?string $description = null, string $mealType = 'breakfast'): string
    {
        try {
            $prompt = $this->buildImagePrompt($dishName, $description, $mealType);
            
            $imageUrl = match($this->provider) {
                'openai' => $this->generateWithOpenAI($prompt),
                'stability' => $this->generateWithStability($prompt),
                'replicate' => $this->generateWithReplicate($prompt),
                'huggingface' => $this->generateWithHuggingFace($prompt),
                default => throw new Exception("Unknown image generation provider: {$this->provider}"),
            };

            // Store locally if configured
            if (config('image_generation.store_locally', false)) {
                $imageUrl = $this->storeImageLocally($imageUrl, $dishName);
            }

            return $imageUrl;
        } catch (Exception $e) {
            Log::error('Failed to generate meal image', [
                'dish_name' => $dishName,
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);

            // Fallback to placeholder if enabled
            if ($this->usePlaceholderFallback) {
                return $this->generatePlaceholderUrl($dishName);
            }

            throw $e;
        }
    }

    /**
     * Generate meal image URL directly from image_prompt.
     *
     * @param string $imagePrompt The detailed image prompt from Llama
     * @return string Image URL
     */
    public function generateMealImageUrlFromPrompt(string $imagePrompt): string
    {
        try {
            $imageUrl = match($this->provider) {
                'openai' => $this->generateWithOpenAI($imagePrompt),
                'stability' => $this->generateWithStability($imagePrompt),
                'replicate' => $this->generateWithReplicate($imagePrompt),
                'huggingface' => $this->generateWithHuggingFace($imagePrompt),
                default => throw new Exception("Unknown image generation provider: {$this->provider}"),
            };

            // Store locally if configured
            if (config('image_generation.store_locally', false)) {
                $imageUrl = $this->storeImageLocally($imageUrl, 'meal_' . uniqid());
            }

            return $imageUrl;
        } catch (Exception $e) {
            Log::error('Failed to generate meal image from prompt', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);

            // Fallback to placeholder if enabled
            if ($this->usePlaceholderFallback) {
                return $this->generatePlaceholderUrl('Meal');
            }

            throw $e;
        }
    }

    /**
     * Generate consumption image URL (for tracking).
     *
     * @param string $dishName
     * @return string
     */
    public function generateConsumptionImageUrl(string $dishName): string
    {
        try {
            $prompt = "A photograph of a prepared {$dishName} meal on a plate, professional food photography, high quality, appetizing";
            return $this->generateMealImageUrl($dishName, $prompt, 'meal');
        } catch (Exception $e) {
            if ($this->usePlaceholderFallback) {
                return $this->generatePlaceholderUrl($dishName, 300, 300);
            }
            throw $e;
        }
    }

    /**
     * Build image generation prompt.
     *
     * @param string $dishName
     * @param string|null $description
     * @param string $mealType
     * @return string
     */
    protected function buildImagePrompt(string $dishName, ?string $description, string $mealType): string
    {
        $basePrompt = "A professional high-quality food photograph of {$dishName}";
        
        if ($description) {
            $basePrompt .= ", {$description}";
        }
        
        $mealTypeContext = match($mealType) {
            'breakfast' => 'breakfast meal, morning food',
            'lunch' => 'lunch meal, midday food',
            'dinner' => 'dinner meal, evening food',
            'snack' => 'snack food',
            default => 'meal',
        };
        
        $basePrompt .= ", {$mealTypeContext}, appetizing, well-lit, restaurant quality, food photography";
        
        return $basePrompt;
    }

    /**
     * Generate image using OpenAI DALL-E.
     *
     * @param string $prompt
     * @return string
     * @throws Exception
     */
    protected function generateWithOpenAI(string $prompt): string
    {
        $apiKey = config('image_generation.openai.api_key');
        if (!$apiKey) {
            throw new Exception('OpenAI API key not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/images/generations', [
            'model' => config('image_generation.openai.model', 'dall-e-3'),
            'prompt' => $prompt,
            'size' => config('image_generation.openai.size', '1024x1024'),
            'quality' => config('image_generation.openai.quality', 'standard'),
            'style' => config('image_generation.openai.style', 'natural'),
            'n' => 1,
        ]);

        if ($response->failed()) {
            throw new Exception("OpenAI API error: " . $response->body());
        }

        $data = $response->json();
        return $data['data'][0]['url'] ?? throw new Exception('No image URL in OpenAI response');
    }

    /**
     * Generate image using Stability AI.
     *
     * @param string $prompt
     * @return string
     * @throws Exception
     */
    protected function generateWithStability(string $prompt): string
    {
        $apiKey = config('image_generation.stability.api_key');
        if (!$apiKey) {
            throw new Exception('Stability AI API key not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.stability.ai/v1/generation/' . config('image_generation.stability.model', 'stable-diffusion-xl-1024-v1-0') . '/text-to-image', [
            'text_prompts' => [
                ['text' => $prompt, 'weight' => 1.0],
            ],
            'width' => config('image_generation.stability.width', 1024),
            'height' => config('image_generation.stability.height', 1024),
            'samples' => 1,
        ]);

        if ($response->failed()) {
            throw new Exception("Stability AI API error: " . $response->body());
        }

        $data = $response->json();
        
        // Stability AI returns base64 images in artifacts
        if (isset($data['artifacts']) && count($data['artifacts']) > 0) {
            $base64Image = $data['artifacts'][0]['base64'] ?? null;
            if ($base64Image) {
                return $this->storeBase64Image($base64Image, 'stability');
            }
        }
        
        throw new Exception('No image in Stability AI response');
    }

    /**
     * Generate image using Replicate.
     *
     * @param string $prompt
     * @return string
     * @throws Exception
     */
    protected function generateWithReplicate(string $prompt): string
    {
        $apiKey = config('image_generation.replicate.api_key');
        if (!$apiKey) {
            throw new Exception('Replicate API key not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => "Token {$apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(120)->post('https://api.replicate.com/v1/predictions', [
            'version' => config('image_generation.replicate.model'),
            'input' => [
                'prompt' => $prompt,
                'width' => 1024,
                'height' => 1024,
            ],
        ]);

        if ($response->failed()) {
            throw new Exception("Replicate API error: " . $response->body());
        }

        $data = $response->json();
        $predictionId = $data['id'] ?? null;
        
        if (!$predictionId) {
            throw new Exception('No prediction ID in Replicate response');
        }

        // Poll for result
        return $this->pollReplicatePrediction($predictionId, $apiKey);
    }

    /**
     * Poll Replicate prediction until complete.
     *
     * @param string $predictionId
     * @param string $apiKey
     * @return string
     * @throws Exception
     */
    protected function pollReplicatePrediction(string $predictionId, string $apiKey): string
    {
        $maxAttempts = 30;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $response = Http::withHeaders([
                'Authorization' => "Token {$apiKey}",
            ])->timeout(10)->get("https://api.replicate.com/v1/predictions/{$predictionId}");

            if ($response->failed()) {
                throw new Exception("Replicate polling error: " . $response->body());
            }

            $data = $response->json();
            $status = $data['status'] ?? 'unknown';

            if ($status === 'succeeded') {
                $output = $data['output'] ?? null;
                if (is_array($output) && isset($output[0])) {
                    return $output[0];
                }
                return $output ?? throw new Exception('No output URL in Replicate response');
            }

            if ($status === 'failed' || $status === 'canceled') {
                throw new Exception("Replicate prediction {$status}");
            }

            sleep(2);
            $attempt++;
        }

        throw new Exception('Replicate prediction timeout');
    }

    /**
     * Generate image using Hugging Face.
     *
     * @param string $prompt
     * @return string
     * @throws Exception
     */
    protected function generateWithHuggingFace(string $prompt): string
    {
        $apiKey = config('image_generation.huggingface.api_key');
        if (!$apiKey) {
            throw new Exception('Hugging Face API key not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->timeout(120)->post('https://api-inference.huggingface.co/models/' . config('image_generation.huggingface.model', 'stabilityai/stable-diffusion-xl-base-1.0'), [
            'inputs' => $prompt,
        ]);

        if ($response->failed()) {
            throw new Exception("Hugging Face API error: " . $response->body());
        }

        $imageData = $response->body();
        return $this->storeBase64Image(base64_encode($imageData), 'huggingface');
    }

    /**
     * Store base64 image locally and return URL.
     *
     * @param string $base64Data
     * @param string $prefix
     * @return string
     */
    protected function storeBase64Image(string $base64Data, string $prefix): string
    {
        $filename = $prefix . '_' . uniqid() . '.png';
        $path = config('image_generation.storage_path', 'public/meal-images') . '/' . $filename;
        
        Storage::put($path, base64_decode($base64Data));
        
        return Storage::url($path);
    }

    /**
     * Download and store remote image locally.
     *
     * @param string $remoteUrl
     * @param string $dishName
     * @return string
     */
    protected function storeImageLocally(string $remoteUrl, string $dishName): string
    {
        try {
            $imageData = Http::timeout(300)->get($remoteUrl)->body();
            $filename = 'meal_' . uniqid() . '_' . $this->cleanDishName($dishName) . '.png';
            $path = config('image_generation.storage_path', 'public/meal-images') . '/' . $filename;
            
            Storage::put($path, $imageData);
            
            return Storage::url($path);
        } catch (Exception $e) {
            Log::warning('Failed to store image locally, using remote URL', [
                'url' => $remoteUrl,
                'error' => $e->getMessage(),
            ]);
            return $remoteUrl;
        }
    }

    /**
     * Generate a placeholder image URL (fallback).
     *
     * @param string $dishName
     * @param int $width
     * @param int $height
     * @return string
     */
    protected function generatePlaceholderUrl(string $dishName, int $width = 400, int $height = 300): string
    {
        $cleanName = $this->cleanDishName($dishName);
        $text = urlencode($cleanName);
        return "https://via.placeholder.com/{$width}x{$height}?text={$text}";
    }

    /**
     * Clean dish name for use in URLs/filenames.
     *
     * @param string $dishName
     * @return string
     */
    protected function cleanDishName(string $dishName): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9\s]/', '', $dishName);
        if (strlen($cleaned) > 30) {
            $cleaned = substr($cleaned, 0, 27) . '...';
        }
        return str_replace(' ', '_', trim($cleaned)) ?: 'Meal';
    }
}

