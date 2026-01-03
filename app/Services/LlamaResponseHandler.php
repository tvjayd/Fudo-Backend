<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LlamaResponseHandler
{
    /**
     * Parse meal plan response from Llama.
     * Handles various response formats and extracts structured data.
     *
     * @param string $response Raw response from Llama
     * @return array Parsed meal plan data
     */
    public function parseMealPlanResponse(string $response): array
    {
        if (empty($response)) {
            return $this->getEmptyPlanStructure();
        }

        // Method 1: Try to extract JSON from response (most common)
        $jsonData = $this->extractJsonFromResponse($response);
        if ($jsonData !== null) {
            return $this->validateAndFormatPlanData($jsonData);
        }

        // Method 2: Try parsing as pure JSON
        $jsonData = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['days'])) {
            return $this->validateAndFormatPlanData($jsonData);
        }

        // Method 3: Extract from markdown code blocks
        $jsonData = $this->extractFromCodeBlocks($response);
        if ($jsonData !== null) {
            return $this->validateAndFormatPlanData($jsonData);
        }

        // Method 4: Fallback - parse text response
        return $this->parseTextResponse($response);
    }

    /**
     * Extract JSON from mixed text response.
     *
     * @param string $response
     * @return array|null
     */
    protected function extractJsonFromResponse(string $response): ?array
    {
        // Find JSON object boundaries
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');

        if ($jsonStart === false || $jsonEnd === false || $jsonEnd <= $jsonStart) {
            return null;
        }

        $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($jsonString, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($data['days'])) {
            return $data;
        }

        return null;
    }

    /**
     * Extract JSON from markdown code blocks.
     *
     * @param string $response
     * @return array|null
     */
    protected function extractFromCodeBlocks(string $response): ?array
    {
        // Look for ```json ... ``` blocks
        if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
            $jsonString = trim($matches[1]);
            $data = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($data['days'])) {
                return $data;
            }
        }

        // Look for ``` ... ``` blocks (without json tag)
        if (preg_match('/```\s*(\{.*?\})\s*```/s', $response, $matches)) {
            $jsonString = trim($matches[1]);
            $data = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($data['days'])) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Parse text response when JSON parsing fails.
     * Extracts basic meal information from text.
     *
     * @param string $response
     * @return array
     */
    protected function parseTextResponse(string $response): array
    {
        Log::warning('Llama response could not be parsed as JSON, using text parsing fallback');

        $days = [];
        $currentDay = null;
        $currentMeal = null;
        $lines = explode("\n", $response);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Detect day/date
            if (preg_match('/day\s*\d+|date[:\s]+(\d{4}-\d{2}-\d{2})/i', $line, $matches)) {
                if ($currentDay !== null && $currentMeal !== null) {
                    $currentDay['meals'][] = $currentMeal;
                    $currentMeal = null;
                }
                if ($currentDay !== null) {
                    $days[] = $currentDay;
                }
                $currentDay = [
                    'date' => $matches[1] ?? now()->toDateString(),
                    'meals' => [],
                ];
                continue;
            }

            // Detect meal type
            if (preg_match('/\b(breakfast|lunch|dinner|snack)\b/i', $line, $matches)) {
                if ($currentMeal !== null) {
                    $currentDay['meals'][] = $currentMeal;
                }
                $currentMeal = [
                    'meal_type' => strtolower($matches[1]),
                    'dish_name' => '',
                    'ingredients' => [],
                    'calories' => null,
                ];
                continue;
            }

            // Extract dish name
            if ($currentMeal !== null && empty($currentMeal['dish_name']) && 
                (stripos($line, 'dish') !== false || stripos($line, 'meal') !== false)) {
                $currentMeal['dish_name'] = preg_replace('/^(dish|meal)[:\s]+/i', '', $line);
                continue;
            }

            // Extract calories
            if (preg_match('/(\d+)\s*calories?/i', $line, $matches)) {
                if ($currentMeal !== null) {
                    $currentMeal['calories'] = (int)$matches[1];
                }
            }
        }

        // Add last meal and day
        if ($currentMeal !== null && $currentDay !== null) {
            $currentDay['meals'][] = $currentMeal;
        }
        if ($currentDay !== null) {
            $days[] = $currentDay;
        }

        return [
            'days' => $days,
            'raw_response' => $response,
            'parsing_method' => 'text_fallback',
        ];
    }

    /**
     * Validate and format parsed plan data.
     *
     * @param array $data
     * @return array
     */
    protected function validateAndFormatPlanData(array $data): array
    {
        if (!isset($data['days']) || !is_array($data['days'])) {
            return $this->getEmptyPlanStructure();
        }

        $formattedDays = [];
        foreach ($data['days'] as $day) {
            $formattedDay = [
                'date' => $day['date'] ?? now()->toDateString(),
                'total_calories' => $day['total_calories'] ?? null,
                'meals' => [],
            ];

            if (isset($day['meals']) && is_array($day['meals'])) {
                foreach ($day['meals'] as $meal) {
                    $formattedMeal = [
                        'meal_type' => $meal['meal_type'] ?? 'breakfast',
                        'option_number' => $this->normalizeNumber($meal['option_number'] ?? null) ?? 1,
                        'suggested_time' => $meal['suggested_time'] ?? null,
                        'dish_name' => $meal['dish_name'] ?? 'Meal',
                        'description' => $meal['description'] ?? null,
                        'image_prompt' => $meal['image_prompt'] ?? $meal['image_description'] ?? null, // Support both image_prompt and image_description
                        'ingredients' => $this->normalizeArray($meal['ingredients'] ?? []),
                        'food_preparation_materials' => $this->normalizeArray($meal['food_preparation_materials'] ?? []),
                        'bread_type' => $meal['bread_type'] ?? null,
                        'rice_type' => $meal['rice_type'] ?? null,
                        'sprouts_material' => $this->normalizeArray($meal['sprouts_material'] ?? null),
                        'calories' => $this->normalizeNumber($meal['calories'] ?? null),
                        'protein' => $this->normalizeNumber($meal['protein'] ?? null),
                        'carbs' => $this->normalizeNumber($meal['carbs'] ?? null),
                        'fats' => $this->normalizeNumber($meal['fats'] ?? null),
                        'cooking_instructions' => $meal['cooking_instructions'] ?? null,
                        'calorie_instructions' => $meal['calorie_instructions'] ?? null,
                    ];

                    $formattedDay['meals'][] = $formattedMeal;
                }
            }

            $formattedDays[] = $formattedDay;
        }

        return [
            'days' => $formattedDays,
            'parsing_method' => 'json',
        ];
    }

    /**
     * Normalize array values.
     *
     * @param mixed $value
     * @return array
     */
    protected function normalizeArray($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value));
        }
        if (is_string($value)) {
            // Try to parse as JSON array
            $parsed = json_decode($value, true);
            if (is_array($parsed)) {
                return array_values(array_filter($parsed));
            }
            // Split by comma
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }
        return [];
    }

    /**
     * Normalize number values.
     *
     * @param mixed $value
     * @return float|null
     */
    protected function normalizeNumber($value): ?float
    {
        if (is_numeric($value)) {
            return (float)$value;
        }
        if (is_string($value)) {
            // Extract number from string
            if (preg_match('/(\d+\.?\d*)/', $value, $matches)) {
                return (float)$matches[1];
            }
        }
        return null;
    }

    /**
     * Group meals by meal_type and option_number for better organization.
     * This helps identify which meals are options for the same meal time.
     *
     * @param array $meals
     * @return array
     */
    public function groupMealsByTypeAndOption(array $meals): array
    {
        $grouped = [];
        foreach ($meals as $meal) {
            $mealType = $meal['meal_type'] ?? 'breakfast';
            $optionNumber = (int)($meal['option_number'] ?? 1);
            
            if (!isset($grouped[$mealType])) {
                $grouped[$mealType] = [];
            }
            
            $grouped[$mealType][$optionNumber] = $meal;
        }
        
        // Sort options within each meal type
        foreach ($grouped as $mealType => $options) {
            ksort($grouped[$mealType]);
        }
        
        return $grouped;
    }

    /**
     * Get empty plan structure.
     *
     * @return array
     */
    protected function getEmptyPlanStructure(): array
    {
        return [
            'days' => [],
            'parsing_method' => 'empty',
        ];
    }

    /**
     * Format response for API return.
     * Transforms parsed data into user-friendly format.
     *
     * @param array $parsedData
     * @param string $planType
     * @return array
     */
    public function formatForApiResponse(array $parsedData, string $planType = 'daily'): array
    {
        $formatted = [
            'plan_type' => $planType,
            'days' => [],
            'summary' => [
                'total_days' => count($parsedData['days'] ?? []),
                'total_meals' => 0,
                'total_calories' => 0,
            ],
        ];

        foreach ($parsedData['days'] ?? [] as $day) {
            $dayMeals = $day['meals'] ?? [];
            $dayCalories = 0;

            foreach ($dayMeals as $meal) {
                $dayCalories += $meal['calories'] ?? 0;
            }

            $formatted['days'][] = [
                'date' => $day['date'] ?? now()->toDateString(),
                'total_calories' => $dayCalories,
                'meals' => $dayMeals,
            ];

            $formatted['summary']['total_meals'] += count($dayMeals);
            $formatted['summary']['total_calories'] += $dayCalories;
        }

        return $formatted;
    }
}

