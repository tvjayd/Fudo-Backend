<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class LlamaService
{
    protected string $baseUrl;
    protected ?string $model;
    protected int $timeout;
    protected array $defaultOptions;

    public function __construct()
    {
        $this->baseUrl = config('llama.base_url', 'http://localhost:11434');
        $this->model = config('llama.model', 'llama3');
        $this->timeout = config('llama.timeout', 300);
        $this->defaultOptions = [
            'temperature' => config('llama.temperature', 0.7),
            'top_p' => config('llama.top_p', 0.9),
            'max_tokens' => config('llama.max_tokens', 2048),
        ];
    }

    /**
     * Generate a completion using Llama 3 model.
     *
     * @param string $prompt
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function generate(string $prompt, array $options = []): array
    {
        try {
            $payload = array_merge($this->defaultOptions, $options, [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
            ]);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/generate", $payload);

            if ($response->failed()) {
                throw new Exception("Llama API error: " . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Llama service error', [
                'error' => $e->getMessage(),
                'prompt' => substr($prompt, 0, 100),
            ]);
            throw $e;
        }
    }

    /**
     * Generate a streamed completion (for real-time responses).
     *
     * @param string $prompt
     * @param array $options
     * @return \Generator
     * @throws Exception
     */
    public function generateStream(string $prompt, array $options = []): \Generator
    {
        try {
            $payload = array_merge($this->defaultOptions, $options, [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => true,
            ]);

            $response = Http::timeout($this->timeout)
                ->withBody(json_encode($payload), 'application/json')
                ->post("{$this->baseUrl}/api/generate");

            if ($response->failed()) {
                throw new Exception("Llama API error: " . $response->body());
            }

            // Parse streamed response
            $body = $response->body();
            $lines = explode("\n", $body);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $data = json_decode($line, true);
                if ($data) {
                    yield $data;
                }
            }
        } catch (Exception $e) {
            Log::error('Llama stream error', [
                'error' => $e->getMessage(),
                'prompt' => substr($prompt, 0, 100),
            ]);
            throw $e;
        }
    }

    /**
     * Check if Llama service is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/tags");

            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * List available models.
     *
     * @return array
     * @throws Exception
     */
    public function listModels(): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/tags");

            if ($response->failed()) {
                throw new Exception("Failed to list models: " . $response->body());
            }

            $data = $response->json();
            return $data['models'] ?? [];
        } catch (Exception $e) {
            Log::error('Failed to list Llama models', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate a chat completion (for conversational AI).
     *
     * @param array $messages Array of messages with 'role' and 'content'
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function chat(array $messages, array $options = []): array
    {
        try {
            $payload = array_merge($this->defaultOptions, $options, [
                'model' => $this->model,
                'messages' => $messages,
                'stream' => false,
            ]);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/chat", $payload);

            if ($response->failed()) {
                throw new Exception("Llama API error: " . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Llama chat error', [
                'error' => $e->getMessage(),
                'messages_count' => count($messages),
            ]);
            throw $e;
        }
    }

    /**
     * Generate fitness/health recommendations based on user health data.
     *
     * @param array $healthData
     * @return string
     * @throws Exception
     */
    public function generateHealthRecommendation(array $healthData): string
    {
        $prompt = $this->buildHealthPrompt($healthData);
        $response = $this->generate($prompt, [
            'temperature' => 0.8,
            'max_tokens' => 1500,
        ]);

        return $response['response'] ?? '';
    }

    /**
     * Build a prompt for health recommendations and meal suggestions based on available ingredients.
     *
     * @param array $healthData
     * @param string $planType
     * @return string
     */
    protected function buildHealthPrompt(array $healthData, string $planType = 'daily'): string
    {
        $days = match($planType) {
            'daily' => 1,
            '2_days' => 2,
            'weekly' => 7,
            default => 1,
        };

        // Build comprehensive input JSON with all user health data
        $inputJson = [
            'age' => $healthData['age'] ?? null,
            'weight' => $healthData['weight'] ?? null,
            'height' => $healthData['height'] ?? null,
            'gender' => $healthData['gender'] ?? null,
            'fitness_plan' => $healthData['fitness_plan'] ?? null,
            'disease' => $healthData['disease'] ?? 'None',
            'lifestyle' => $healthData['lifestyle'] ?? 'Moderate',
            'workout_type' => $healthData['workout_type'] ?? null,
            'workout_intensity' => $healthData['workout_intense_type'] ?? 'High',
            'workout_time_minutes' => $this->extractWorkoutTimeMinutes($healthData['workout_time'] ?? null),
            'meal_type' => $healthData['meal_type'] ?? null,
            'allergies' => $healthData['allergies'] ?? [],
            'ingredients' => $this->formatIngredients($healthData),
            'food_preparation' => $this->formatFoodPreparation($healthData),
            'bread_type' => $healthData['bread_type'] ?? null,
            'rice_type' => $healthData['rice_type'] ?? null,
            'sprouts_material' => $this->formatSprouts($healthData['sprouts_material'] ?? null),
        ];

        $inputJsonString = json_encode($inputJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Build the main prompt using your specific requirements
        $prompt = "You are an AI Food Decision Optimizer (FDO), certified nutritionist, and fitness diet planner.\n\n";
        $prompt .= "STRICT RULES (DO NOT BREAK):\n";
        $prompt .= "1. Output MUST be valid JSON ONLY.\n";
        $prompt .= "2. breakfast, lunch, and dinner MUST each contain 3 to 4 meal options.\n";
        $prompt .= "3. Each meal option MUST include: meal_name, ingredients, calories (kcal), protein (g), carbs (g), fat (g), fiber (g), image_prompt, and image_url.\n";
        $prompt .= "4. Avoid ALL allergens completely (e.g., peanuts).\n";
        $prompt .= "5. Use ONLY the provided ingredients. Salt and water are allowed implicitly.\n";
        $prompt .= "6. Nutrition values must be realistic and optimized for the given fitness goal.\n";
        $prompt .= "7. Meals MUST support Muscle Building with High-Intensity Gym workouts.\n";
        $prompt .= "8. image_url MUST represent the EXACT meal and its main ingredients.\n";
        $prompt .= "9. image_url MUST visually match the ingredients listed for that meal.\n";
        $prompt .= "10. image_url MUST be a SINGLE direct image URL (not an array).\n";
        $prompt .= "11. image_url MUST follow public food image patterns (Pexels or Unsplash).\n";
        $prompt .= "12. image_prompt MUST clearly describe the meal and ingredients for accurate image retrieval.\n";
        $prompt .= "13. DO NOT reuse the same image_url for multiple meals.\n";
        $prompt .= "14. DO NOT return placeholder or fake URLs.\n";
        $prompt .= "15. DO NOT include explanation text outside JSON.\n\n";

        $prompt .= "--------------------------------------------------\n";
        $prompt .= "USER INPUT:\n" . $inputJsonString . "\n";
        $prompt .= "--------------------------------------------------\n\n";

        $prompt .= "RESPONSE FORMAT (STRICT JSON ONLY):\n";
        $prompt .= "{\n";
        $prompt .= "  \"status\": \"success\",\n";
        $prompt .= "  \"message\": \"Meal plan generated successfully\",\n";
        $prompt .= "  \"data\": {\n";
        $prompt .= "    \"breakfast\": [\n";
        $prompt .= "      {\n";
        $prompt .= "        \"meal_name\": \"Meal Name\",\n";
        $prompt .= "        \"ingredients\": [\"Ingredient 1\", \"Ingredient 2\"],\n";
        $prompt .= "        \"calories\": 0,\n";
        $prompt .= "        \"protein\": 0,\n";
        $prompt .= "        \"carbs\": 0,\n";
        $prompt .= "        \"fat\": 0,\n";
        $prompt .= "        \"fiber\": 0,\n";
        $prompt .= "        \"image_prompt\": \"High quality food photography of [meal name] made with [ingredients], clean background, realistic lighting\",\n";
        $prompt .= "        \"image_url\": \"https://images.pexels.com/photos/XXXXXX/pexels-photo-XXXXXX.jpeg\"\n";
        $prompt .= "      }\n";
        $prompt .= "    ],\n";
        $prompt .= "    \"lunch\": [ /* 3-4 options */ ],\n";
        $prompt .= "    \"dinner\": [ /* 3-4 options */ ]\n";
        $prompt .= "  }\n";
        $prompt .= "}\n\n";

        $prompt .= "FINAL WARNING:\n";
        $prompt .= "- Output ONLY JSON.\n";
        $prompt .= "- image_url MUST clearly match the meal ingredients.\n";
        $prompt .= "- No placeholders, no text outside JSON.\n";

        return $prompt;
    }

    /**
     * Generate meal plan using Llama 3.
     *
     * @param array $healthData
     * @param string $planType (daily, 2_days, weekly)
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function generateMealPlan(array $healthData, string $planType = 'daily', array $options = []): array
    {
        $prompt = $this->buildMealPlanPrompt($healthData, $planType);
        
        $generationOptions = array_merge([
            'temperature' => 0.8,
            'max_tokens' => 3000,
        ], $options);

        $response = $this->generate($prompt, $generationOptions);
        
        // Convert AI string response back to an object/array
        $decodedAiResponse = json_decode($response['response'] ?? '{}', true);

        return [
            'success' => true,
            'data' => $decodedAiResponse ?? [],
            'model' => $response['model'] ?? null
        ];
    }

    /**
     * Build a comprehensive meal plan prompt using FDO (Food Decision Optimizer) format.
     *
     * @param array $healthData
     * @param string $planType
     * @return string
     */
    protected function buildMealPlanPrompt(array $healthData, string $planType = 'daily'): string
    {
        // Build the dynamic input JSON based on user health data
        $inputJson = [
            'age' => $healthData['age'] ?? null,
            'weight' => $healthData['weight'] ?? null,
            'height' => $healthData['height'] ?? null,
            'gender' => $healthData['gender'] ?? null,
            'fitness_plan' => $healthData['fitness_plan'] ?? 'Muscle Building',
            'disease' => $healthData['disease'] ?? 'None',
            'lifestyle' => $healthData['lifestyle'] ?? 'Active',
            'workout_type' => $healthData['workout_type'] ?? 'Gym',
            'workout_intensity' => $healthData['workout_intense_type'] ?? 'High',
            'workout_time_minutes' => $this->extractWorkoutTimeMinutes($healthData['workout_time'] ?? null),
            'meal_type' => $healthData['meal_type'] ?? 'Non Veg',
            'allergies' => $healthData['allergies'] ?? [],
            'ingredients' => $this->formatIngredients($healthData),
            'food_preparation' => $this->formatFoodPreparation($healthData),
            'bread_type' => $healthData['bread_type'] ?? null,
            'rice_type' => $healthData['rice_type'] ?? null,
            'sprouts_material' => $this->formatSprouts($healthData['sprouts_material'] ?? null),
        ];

        $inputJsonString = json_encode($inputJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Build the finalized prompt string
        $prompt = "You are an AI Food Decision Optimizer (FDO), certified nutritionist, and fitness diet planner.\n\n";
        $prompt .= "STRICT RULES (DO NOT BREAK):\n";
        $prompt .= "1. Output MUST be valid JSON ONLY.\n";
        $prompt .= "2. breakfast, lunch, and dinner MUST each contain 3 to 4 meal options.\n";
        $prompt .= "3. Each meal option MUST include:\n";
        $prompt .= "   - meal_name\n";
        $prompt .= "   - ingredients (ONLY ingredients used in that meal)\n";
        $prompt .= "   - calories (kcal)\n";
        $prompt .= "   - protein (g)\n";
        $prompt .= "   - carbs (g)\n";
        $prompt .= "   - fat (g)\n";
        $prompt .= "   - fiber (g)\n";
        $prompt .= "   - image_prompt\n";
        $prompt .= "   - image_url\n";
        $prompt .= "4. Avoid ALL allergens completely (e.g., peanuts).\n";
        $prompt .= "5. Use ONLY the provided ingredients. Salt and water are allowed implicitly.\n";
        $prompt .= "6. Nutrition values must be realistic and optimized for the given fitness goal.\n";
        $prompt .= "7. Meals MUST support Muscle Building with High-Intensity Gym workouts.\n";
        $prompt .= "8. image_url MUST represent the EXACT meal and its main ingredients.\n";
        $prompt .= "9. image_url MUST visually match the ingredients listed for that meal.\n";
        $prompt .= "10. image_url MUST be a SINGLE direct image URL (not an array).\n";
        $prompt .= "11. image_url MUST follow public food image patterns like:\n";
        $prompt .= "    https://images.pexels.com/photos/XXXXX/pexels-photo-XXXXX.jpeg\n";
        $prompt .= "    https://images.unsplash.com/photos/XXXXX\n";
        $prompt .= "12. image_prompt MUST clearly describe the meal and ingredients for accurate image retrieval.\n";
        $prompt .= "13. DO NOT reuse the same image_url for multiple meals.\n";
        $prompt .= "14. DO NOT return placeholder or fake URLs.\n";
        $prompt .= "15. DO NOT include explanation text outside JSON.\n\n";

        $prompt .= "--------------------------------------------------\n";
        $prompt .= "USER INPUT:\n";
        $prompt .= $inputJsonString . "\n";
        $prompt .= "--------------------------------------------------\n\n";

        $prompt .= "RESPONSE FORMAT (STRICT JSON ONLY):\n";
        $prompt .= "{\n";
        $prompt .= "  \"status\": \"success\",\n";
        $prompt .= "  \"message\": \"Meal plan generated successfully\",\n";
        $prompt .= "  \"data\": {\n";
        $prompt .= "    \"breakfast\": [\n";
        $prompt .= "      {\n";
        $prompt .= "        \"meal_name\": \"Meal Name\",\n";
        $prompt .= "        \"ingredients\": [\"Ingredient 1\", \"Ingredient 2\"],\n";
        $prompt .= "        \"calories\": 0,\n";
        $prompt .= "        \"protein\": 0,\n";
        $prompt .= "        \"carbs\": 0,\n";
        $prompt .= "        \"fat\": 0,\n";
        $prompt .= "        \"fiber\": 0,\n";
        $prompt .= "        \"image_prompt\": \"High quality food photography of [meal name] made with [ingredients], clean background, realistic lighting\",\n";
        $prompt .= "        \"image_url\": \"https://images.pexels.com/photos/XXXXXX/pexels-photo-XXXXXX.jpeg\"\n";
        $prompt .= "      }\n";
        $prompt .= "    ],\n";
        $prompt .= "    \"lunch\": [\n";
        $prompt .= "       { /* 3-4 options */ }\n";
        $prompt .= "    ],\n";
        $prompt .= "    \"dinner\": [\n";
        $prompt .= "       { /* 3-4 options */ }\n";
        $prompt .= "    ]\n";
        $prompt .= "  }\n";
        $prompt .= "}\n\n";

        $prompt .= "FINAL WARNING:\n";
        $prompt .= "- Output ONLY JSON.\n";
        $prompt .= "- image_url MUST clearly match the meal ingredients.\n";
        $prompt .= "- No placeholders, no text outside JSON.\n\n";
        $prompt .= "Response (JSON ONLY):";

        return $prompt;
    }
    /**
     * Extract workout time in minutes from workout_time string.
     *
     * @param string|null $workoutTime
     * @return int|null
     */
    protected function extractWorkoutTimeMinutes(?string $workoutTime): ?int
    {
        if (!$workoutTime) {
            return null;
        }

        // Try to extract numbers from the string
        if (preg_match('/(\d+)/', $workoutTime, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * Format ingredients into structured format.
     *
     * @param array $healthData
     * @return array
     */
    protected function formatIngredients(array $healthData): array
    {
        $ingredients = [];
        
        // Handle array format
        if (isset($healthData['ingredients']) && is_array($healthData['ingredients'])) {
            $ingredientCategory = $healthData['ingredient_category'] ?? 'veggies';
            
            if ($ingredientCategory === 'veggies') {
                $ingredients['vegetables'] = $healthData['ingredients'];
            } elseif ($ingredientCategory === 'mass') {
                $ingredients['meat'] = $healthData['ingredients'];
            } else {
                // Try to categorize automatically
                $vegetables = [];
                $meat = [];
                $commonMeat = ['chicken', 'beef', 'pork', 'fish', 'egg', 'eggs', 'turkey', 'lamb'];
                
                foreach ($healthData['ingredients'] as $ingredient) {
                    $lower = strtolower($ingredient);
                    if (in_array($lower, $commonMeat) || str_contains($lower, 'chicken') || str_contains($lower, 'fish') || str_contains($lower, 'egg')) {
                        $meat[] = $ingredient;
                    } else {
                        $vegetables[] = $ingredient;
                    }
                }
                
                if (!empty($vegetables)) {
                    $ingredients['vegetables'] = $vegetables;
                }
                if (!empty($meat)) {
                    $ingredients['meat'] = $meat;
                }
            }
        }
        
        return $ingredients;
    }

    /**
     * Format food preparation materials into structured format.
     *
     * @param array $healthData
     * @return array
     */
    protected function formatFoodPreparation(array $healthData): array
    {
        $preparation = [];
        
        if (isset($healthData['food_preparation_materials']) && is_array($healthData['food_preparation_materials'])) {
            $oils = [];
            $spices = [];
            
            $commonOils = ['oil', 'olive', 'coconut', 'sunflower', 'mustard', 'sesame', 'avocado'];
            
            foreach ($healthData['food_preparation_materials'] as $material) {
                $lower = strtolower($material);
                $isOil = false;
                
                foreach ($commonOils as $oil) {
                    if (str_contains($lower, $oil)) {
                        $oils[] = $material;
                        $isOil = true;
                        break;
                    }
                }
                
                if (!$isOil) {
                    $spices[] = $material;
                }
            }
            
            if (!empty($oils)) {
                $preparation['oil'] = $oils;
            }
            if (!empty($spices)) {
                $preparation['spices'] = $spices;
            }
        }
        
        return $preparation;
    }

    /**
     * Format sprouts material.
     *
     * @param mixed $sproutsMaterial
     * @return array|null
     */
    protected function formatSprouts($sproutsMaterial): ?array
    {
        if (!$sproutsMaterial) {
            return null;
        }
        
        if (is_array($sproutsMaterial)) {
            return $sproutsMaterial;
        }
        
        if (is_string($sproutsMaterial)) {
            return [$sproutsMaterial];
        }
        
        return null;
    }

    /**
     * Generate meal suggestions based on previous feedback.
     *
     * @param array $healthData
     * @param array $previousFeedback
     * @param string $planType
     * @return array
     * @throws Exception
     */
    public function generateMealPlanWithFeedback(array $healthData, array $previousFeedback, string $planType = 'daily'): array
    {
        $prompt = $this->buildMealPlanPrompt($healthData, $planType);
        
        // Add feedback context
        $prompt .= "\n\nIMPORTANT: Previous Day Feedback:\n";
        if (isset($previousFeedback['overall_satisfaction'])) {
            $prompt .= "Overall Satisfaction: {$previousFeedback['overall_satisfaction']}/5\n";
        }
        if (isset($previousFeedback['liked_meals'])) {
            $prompt .= "Liked Meals: {$previousFeedback['liked_meals']}\n";
        }
        if (isset($previousFeedback['disliked_meals'])) {
            $prompt .= "Disliked Meals: {$previousFeedback['disliked_meals']}\n";
        }
        if (isset($previousFeedback['suggestions'])) {
            $prompt .= "User Suggestions: {$previousFeedback['suggestions']}\n";
        }
        if (isset($previousFeedback['hunger_level_met'])) {
            $prompt .= "Hunger Level Met: " . ($previousFeedback['hunger_level_met'] ? 'Yes' : 'No') . "\n";
        }
        if (isset($previousFeedback['energy_level'])) {
            $prompt .= "Energy Level: {$previousFeedback['energy_level']}/5\n";
        }
        
        $prompt .= "\nPlease adjust the meal plan based on this feedback. Include more of what the user liked and avoid what they disliked.\n";
        $prompt .= "Response:";

        $response = $this->generate($prompt, [
            'temperature' => 0.8,
            'max_tokens' => 3000,
        ]);

        return [
            'response' => $response['response'] ?? '',
            'model' => $response['model'] ?? null,
            'prompt' => $prompt,
        ];
    }

    /**
     * Generate next day meal plan based on yesterday's consumption.
     *
     * @param array $healthData
     * @param array $consumptionSummary Comprehensive consumption analysis from yesterday
     * @param array $previousFeedback Optional feedback from yesterday
     * @param string $planType
     * @return array
     * @throws Exception
     */
    public function generateNextDayMealPlan(array $healthData, array $consumptionSummary, ?array $previousFeedback = null, string $planType = 'daily'): array
    {
        $prompt = $this->buildMealPlanPrompt($healthData, $planType);
        
        // Extract consumption data
        $targetCalories = $consumptionSummary['target_calories'] ?? 0;
        $consumedCalories = $consumptionSummary['consumed_calories'] ?? 0;
        $deficit = $consumptionSummary['deficit'] ?? 0;
        $surplus = $consumptionSummary['surplus'] ?? 0;
        $percentage = $consumptionSummary['percentage_of_target'] ?? 0;
        $mealBreakdown = $consumptionSummary['meal_breakdown'] ?? [];
        $skippedMeals = $consumptionSummary['skipped_meals'] ?? [];
        $timingPatterns = $consumptionSummary['timing_patterns'] ?? [];
        $consumptionDetails = $consumptionSummary['consumption_details'] ?? [];
        
        // Add comprehensive yesterday's consumption analysis
        $prompt .= "\n\n=== YESTERDAY'S CONSUMPTION ANALYSIS ===\n\n";
        
        // Calorie Summary
        $prompt .= "CALORIE ANALYSIS:\n";
        $prompt .= "- Target Calories: {$targetCalories}\n";
        $prompt .= "- Consumed Calories: {$consumedCalories}\n";
        $prompt .= "- Percentage of Target: {$percentage}%\n";
        
        if ($deficit > 0) {
            $prompt .= "- Calorie Deficit: {$deficit} calories (user under-ate)\n";
            $prompt .= "  → ACTION: Increase calories in today's plan to compensate\n";
        } elseif ($surplus > 0) {
            $prompt .= "- Calorie Surplus: {$surplus} calories (user over-ate)\n";
            $prompt .= "  → ACTION: Reduce calories or suggest lighter meals today\n";
        } else {
            $prompt .= "- Calories on target\n";
        }
        
        // Meal Type Breakdown
        $prompt .= "\nMEAL TYPE BREAKDOWN:\n";
        foreach ($mealBreakdown as $mealType => $stats) {
            $prompt .= "- {$mealType}: ";
            $prompt .= "Ate: {$stats['ate']}, ";
            $prompt .= "Skipped: {$stats['skipped']}, ";
            $prompt .= "Not Ate: {$stats['not_ate']}, ";
            $prompt .= "Calories: {$stats['calories']}\n";
        }
        
        // Skipped Meals Analysis
        if (!empty($skippedMeals)) {
            $prompt .= "\nSKIPPED MEALS:\n";
            foreach ($skippedMeals as $skipped) {
                $prompt .= "- {$skipped['meal_type']}: {$skipped['dish_name']} (suggested at {$skipped['suggested_time']})\n";
            }
            $prompt .= "  → ACTION: Suggest easier/quicker options or adjust meal times\n";
        }
        
        // Timing Patterns
        $avgDelay = 0;
        if (!empty($timingPatterns)) {
            $prompt .= "\nMEAL TIMING PATTERNS:\n";
            $delayCount = 0;
            foreach ($timingPatterns as $pattern) {
                $diff = $pattern['difference_minutes'] ?? 0;
                $prompt .= "- {$pattern['meal_type']}: Suggested {$pattern['suggested_time']}, Actual {$pattern['actual_time']} (";
                if ($diff > 0) {
                    $prompt .= "{$diff} min ";
                    if ($pattern['actual_time'] > $pattern['suggested_time']) {
                        $prompt .= "late";
                    } else {
                        $prompt .= "early";
                    }
                } else {
                    $prompt .= "on time";
                }
                $prompt .= ")\n";
                if ($diff > 0) {
                    $avgDelay += $diff;
                    $delayCount++;
                }
            }
            if ($delayCount > 0) {
                $avgDelay = round($avgDelay / $delayCount);
                $prompt .= "  → Average timing difference: {$avgDelay} minutes\n";
                $prompt .= "  → ACTION: Adjust suggested meal times based on user's actual eating patterns\n";
            }
        }
        
        // Detailed Consumption
        $prompt .= "\nDETAILED CONSUMPTION:\n";
        foreach ($consumptionDetails as $consumed) {
            $mealName = $consumed['dish_name'] ?? 'Unknown meal';
            $calories = $consumed['consumed_calories'] ?? $consumed['calories'] ?? 0;
            $mealType = $consumed['meal_type'] ?? 'meal';
            $status = $consumed['status'] ?? 'pending';
            
            $prompt .= "- {$mealType}: {$mealName} ({$calories} calories) - Status: {$status}";
            
            if (isset($consumed['actual_time']) && isset($consumed['suggested_time'])) {
                $prompt .= " - Time: {$consumed['actual_time']} (suggested: {$consumed['suggested_time']})";
            }
            
            if (isset($consumed['modifications']) && !empty($consumed['modifications'])) {
                $prompt .= " - Modifications: {$consumed['modifications']}";
            }
            
            if (isset($consumed['portion_size']) && $consumed['portion_size'] !== 'full') {
                $prompt .= " - Portion: {$consumed['portion_size']}";
            }
            
            $prompt .= "\n";
        }
        
        // Add feedback if available
        if ($previousFeedback) {
            $prompt .= "\n=== USER FEEDBACK ===\n";
            if (isset($previousFeedback['overall_satisfaction'])) {
                $prompt .= "Overall Satisfaction: {$previousFeedback['overall_satisfaction']}/5\n";
            }
            if (isset($previousFeedback['liked_meals'])) {
                $prompt .= "Liked Meals: {$previousFeedback['liked_meals']}\n";
                $prompt .= "  → ACTION: Include similar meals in today's plan\n";
            }
            if (isset($previousFeedback['disliked_meals'])) {
                $prompt .= "Disliked Meals: {$previousFeedback['disliked_meals']}\n";
                $prompt .= "  → ACTION: Avoid these meals completely\n";
            }
            if (isset($previousFeedback['suggestions'])) {
                $prompt .= "User Suggestions: {$previousFeedback['suggestions']}\n";
            }
            if (isset($previousFeedback['hunger_level_met'])) {
                $prompt .= "Hunger Level Met: " . ($previousFeedback['hunger_level_met'] ? 'Yes' : 'No') . "\n";
                if (!$previousFeedback['hunger_level_met']) {
                    $prompt .= "  → ACTION: Increase portion sizes or add more filling meals\n";
                }
            }
            if (isset($previousFeedback['energy_level'])) {
                $prompt .= "Energy Level: {$previousFeedback['energy_level']}/5\n";
            }
        }
        
        // Intelligent adjustments
        $prompt .= "\n=== INTELLIGENT ADJUSTMENTS FOR TODAY ===\n";
        $prompt .= "Based on the analysis above, generate today's meal plan with these considerations:\n\n";
        
        if ($deficit > 0) {
            $prompt .= "1. CALORIE ADJUSTMENT: User under-ate by {$deficit} calories. Increase today's total calories by approximately {$deficit} calories.\n";
        } elseif ($surplus > 0) {
            $prompt .= "1. CALORIE ADJUSTMENT: User over-ate by {$surplus} calories. Reduce today's total calories or suggest lighter, lower-calorie meals.\n";
        }
        
        if (!empty($skippedMeals)) {
            $prompt .= "2. SKIPPED MEALS: User skipped " . count($skippedMeals) . " meal(s). Suggest easier-to-prepare or quicker options for those meal types.\n";
        }
        
        if (!empty($timingPatterns)) {
            $prompt .= "3. TIMING ADJUSTMENT: Adjust suggested meal times based on user's actual eating patterns (average difference: {$avgDelay} minutes).\n";
        }
        
        $prompt .= "4. VARIETY: Avoid repeating the exact same meals from yesterday.\n";
        $prompt .= "5. INGREDIENTS: Use remaining ingredients from user's available list.\n";
        $fitnessPlan = $healthData['fitness_plan'] ?? 'not specified';
        $prompt .= "6. FITNESS GOAL: Maintain progress toward user's fitness goal ({$fitnessPlan}).\n";
        
        if ($previousFeedback && isset($previousFeedback['liked_meals'])) {
            $prompt .= "7. PREFERENCES: Include meals similar to what user liked: {$previousFeedback['liked_meals']}\n";
        }
        
        if ($previousFeedback && isset($previousFeedback['disliked_meals'])) {
            $prompt .= "8. AVOID: Do not include meals similar to: {$previousFeedback['disliked_meals']}\n";
        }
        
        $prompt .= "\nGenerate today's meal plan following the same JSON format as specified in the main prompt.\n";
        $prompt .= "Response (JSON ONLY):";

        $response = $this->generate($prompt, [
            'temperature' => 0.8,
            'max_tokens' => 3000,
        ]);
        
        return [
            'response' => $response['response'] ?? '',
            'model' => $response['model'] ?? null,
            'prompt' => $prompt,
        ];
    }
}

