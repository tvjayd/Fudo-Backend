<?php

namespace App\Http\Controllers;

use App\Models\UserRecipe;
use App\Services\LlamaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class UserRecipeController extends BaseController
{
    protected LlamaService $llamaService;

    public function __construct(LlamaService $llamaService)
    {
        $this->llamaService = $llamaService;
    }

    /**
     * Generate a recipe suggestion for the user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'meal_type' => 'nullable|in:breakfast,lunch,dinner,snack',
            'preferred_ingredients' => 'nullable|array',
            'preferred_ingredients.*' => 'string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $healthDetail = $user->healthDetail;
            if (!$healthDetail) {
                return $this->notFoundResponse('Health details not found. Please update your health profile first.');
            }

            $healthData = $healthDetail->toArray();
            $mealType = $request->input('meal_type', 'lunch');
            $preferredIngredients = $request->input('preferred_ingredients', []);

            // Build recipe generation prompt using user's ingredients from health details
            $ingredients = $healthData['ingredients'] ?? [];
            if (!empty($preferredIngredients)) {
                $ingredients = array_merge($ingredients, $preferredIngredients);
            }

            $prompt = "You are an AI Food Decision Optimizer (FDO), certified nutritionist, and fitness diet planner.\n\n";
            $prompt .= "Generate a SINGLE RECIPE for {$mealType} using ONLY the following available ingredients:\n\n";
            $prompt .= "AVAILABLE INGREDIENTS:\n";
            foreach ($ingredients as $ingredient) {
                $prompt .= "- {$ingredient}\n";
            }

            if (!empty($healthData['food_preparation_materials'])) {
                $prompt .= "\nAVAILABLE FOOD PREPARATION MATERIALS:\n";
                $materials = is_array($healthData['food_preparation_materials']) 
                    ? $healthData['food_preparation_materials'] 
                    : [$healthData['food_preparation_materials']];
                foreach ($materials as $material) {
                    $prompt .= "- {$material}\n";
                }
            }

            if (!empty($healthData['bread_type'])) {
                $prompt .= "\nBREAD TYPE: {$healthData['bread_type']}\n";
            }

            if (!empty($healthData['rice_type'])) {
                $prompt .= "\nRICE TYPE: {$healthData['rice_type']}\n";
            }

            $prompt .= "\nUSER PROFILE:\n";
            $prompt .= "- Age: " . ($healthData['age'] ?? 'N/A') . "\n";
            $prompt .= "- Fitness Goal: " . ($healthData['fitness_plan'] ?? 'N/A') . "\n";
            $prompt .= "- Meal Type Preference: " . ($healthData['meal_type'] ?? 'N/A') . "\n";
            if (!empty($healthData['allergies'])) {
                $prompt .= "- Allergies: {$healthData['allergies']}\n";
                $prompt .= "  → CRITICAL: Completely avoid these allergens\n";
            }

            $prompt .= "\nOUTPUT FORMAT (STRICT JSON ONLY):\n";
            $prompt .= "{\n";
            $prompt .= "  \"recipe_name\": \"Creative dish name using available ingredients\",\n";
            $prompt .= "  \"description\": \"Brief description of the dish\",\n";
            $prompt .= "  \"ingredients\": [\"Only from available ingredients list\"],\n";
            $prompt .= "  \"food_preparation_materials\": [\"Only from available materials\"],\n";
            $prompt .= "  \"bread_type\": \"string or null\",\n";
            $prompt .= "  \"rice_type\": \"string or null\",\n";
            $prompt .= "  \"sprouts_material\": [\"array or null\"],\n";
            $prompt .= "  \"calories\": number,\n";
            $prompt .= "  \"protein\": number,\n";
            $prompt .= "  \"carbs\": number,\n";
            $prompt .= "  \"fats\": number,\n";
            $prompt .= "  \"cooking_instructions\": \"Step-by-step instructions using available materials\",\n";
            $prompt .= "  \"calorie_instructions\": \"How this fits user's fitness goal\",\n";
            $prompt .= "  \"serving_size\": number,\n";
            $prompt .= "  \"prep_time\": number (minutes),\n";
            $prompt .= "  \"cook_time\": number (minutes)\n";
            $prompt .= "}\n\n";

            $prompt .= "REQUIREMENTS:\n";
            $prompt .= "- Use ONLY ingredients from the available list\n";
            $prompt .= "- Avoid allergens completely\n";
            $prompt .= "- Calculate accurate nutrition based on fitness goal\n";
            $prompt .= "- Make it realistic, healthy, and easy to prepare\n";
            $prompt .= "- Output ONLY valid JSON. No markdown, no explanations, no code blocks.\n\n";
            $prompt .= "Response (JSON ONLY):";

            // Generate recipe using Llama
            $llamaResponse = $this->llamaService->generate($prompt, [
                'temperature' => 0.8,
                'max_tokens' => 2000,
            ]);

            // Parse the response
            $parsedRecipe = $this->parseRecipeResponse($llamaResponse['response'] ?? '');

            return $this->successResponse([
                'recipe' => $parsedRecipe,
                'llama_response' => $llamaResponse['response'] ?? '',
                'model' => $llamaResponse['model'] ?? config('llama.model', 'llama3'),
                'used_ingredients' => $ingredients,
            ], 'Recipe generated successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate recipe: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a selected recipe for the user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recipe_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ingredients' => 'required|array',
            'ingredients.*' => 'string',
            'food_preparation_materials' => 'nullable|array',
            'food_preparation_materials.*' => 'string',
            'bread_type' => 'nullable|string',
            'rice_type' => 'nullable|string',
            'sprouts_material' => 'nullable|array',
            'sprouts_material.*' => 'string',
            'calories' => 'nullable|integer|min:0',
            'protein' => 'nullable|numeric|min:0',
            'carbs' => 'nullable|numeric|min:0',
            'fats' => 'nullable|numeric|min:0',
            'cooking_instructions' => 'required|string',
            'calorie_instructions' => 'nullable|string',
            'serving_size' => 'nullable|integer|min:1',
            'prep_time' => 'nullable|integer|min:0',
            'cook_time' => 'nullable|integer|min:0',
            'llama_prompt' => 'nullable|string',
            'llama_response' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $recipe = UserRecipe::create(array_merge($request->all(), [
                'user_id' => $user->id,
            ]));

            return $this->successResponse([
                'recipe' => $recipe,
            ], 'Recipe stored successfully', 201);
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to store recipe: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get user's stored recipes.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $recipes = $user->userRecipes()->latest()->get();

            return $this->successResponse([
                'recipes' => $recipes,
            ], 'Recipes retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve recipes: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Parse the Llama recipe response into structured data.
     * Handles both JSON and text formats.
     *
     * @param string $response
     * @return array
     */
    protected function parseRecipeResponse(string $response): array
    {
        // Try to parse as JSON first
        $jsonData = json_decode(trim($response), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
            // Clean up the parsed JSON
            return [
                'recipe_name' => $jsonData['recipe_name'] ?? '',
                'description' => $jsonData['description'] ?? '',
                'ingredients' => $jsonData['ingredients'] ?? [],
                'food_preparation_materials' => $jsonData['food_preparation_materials'] ?? [],
                'bread_type' => $jsonData['bread_type'] ?? null,
                'rice_type' => $jsonData['rice_type'] ?? null,
                'sprouts_material' => $jsonData['sprouts_material'] ?? null,
                'calories' => $jsonData['calories'] ?? null,
                'protein' => $jsonData['protein'] ?? null,
                'carbs' => $jsonData['carbs'] ?? null,
                'fats' => $jsonData['fats'] ?? null,
                'cooking_instructions' => $jsonData['cooking_instructions'] ?? '',
                'calorie_instructions' => $jsonData['calorie_instructions'] ?? null,
                'serving_size' => $jsonData['serving_size'] ?? null,
                'prep_time' => $jsonData['prep_time'] ?? null,
                'cook_time' => $jsonData['cook_time'] ?? null,
            ];
        }

        // Fallback: Parse as text format
        $lines = explode("\n", $response);
        $recipe = [
            'recipe_name' => '',
            'description' => '',
            'ingredients' => [],
            'food_preparation_materials' => [],
            'cooking_instructions' => '',
            'calories' => null,
            'protein' => null,
            'carbs' => null,
            'fats' => null,
            'serving_size' => null,
            'prep_time' => null,
            'cook_time' => null,
        ];

        $currentSection = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match('/recipe[\s_]?name:?\s*(.+)/i', $line, $matches)) {
                $recipe['recipe_name'] = trim($matches[1]);
            } elseif (preg_match('/description:?\s*(.+)/i', $line, $matches)) {
                $recipe['description'] = trim($matches[1]);
            } elseif (preg_match('/ingredients?:/i', $line)) {
                $currentSection = 'ingredients';
            } elseif (preg_match('/instructions?:/i', $line) || preg_match('/cooking[\s_]?instructions?:/i', $line)) {
                $currentSection = 'instructions';
            } elseif (preg_match('/nutrition/i', $line)) {
                $currentSection = 'nutrition';
            } elseif ($currentSection === 'ingredients' && (str_starts_with($line, '-') || str_starts_with($line, '*'))) {
                $recipe['ingredients'][] = trim(substr($line, 1));
            } elseif ($currentSection === 'instructions') {
                $recipe['cooking_instructions'] .= $line . "\n";
            } elseif (preg_match('/calories?:?\s*(\d+)/i', $line, $matches)) {
                $recipe['calories'] = (int)$matches[1];
            } elseif (preg_match('/protein:?\s*([\d.]+)/i', $line, $matches)) {
                $recipe['protein'] = (float)$matches[1];
            } elseif (preg_match('/carbs?:?\s*([\d.]+)/i', $line, $matches)) {
                $recipe['carbs'] = (float)$matches[1];
            } elseif (preg_match('/fats?:?\s*([\d.]+)/i', $line, $matches)) {
                $recipe['fats'] = (float)$matches[1];
            }
        }

        $recipe['cooking_instructions'] = trim($recipe['cooking_instructions']);

        return $recipe;
    }
}
