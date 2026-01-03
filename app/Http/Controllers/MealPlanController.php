<?php

namespace App\Http\Controllers;

use App\Http\Resources\MealPlanResource;
use App\Http\Resources\MealResource;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Services\LlamaService;
use App\Services\LlamaResponseHandler;
use App\Services\MealImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class MealPlanController extends BaseController
{
    protected LlamaService $llamaService;
    protected LlamaResponseHandler $responseHandler;
    protected MealImageService $imageService;

    public function __construct(LlamaService $llamaService, LlamaResponseHandler $responseHandler, MealImageService $imageService)
    {
        $this->llamaService = $llamaService;
        $this->responseHandler = $responseHandler;
        $this->imageService = $imageService;
    }

    /**
     * Generate a new meal plan using Llama.
     * Handles both regular meal plans and next-day meal plans based on 'date_type' parameter.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generate(Request $request): JsonResponse
    {
        $request->merge([
            'date_type' => $request->date_type ?? 'today',
        ]);

        $validator = Validator::make($request->all(), [
            'plan_type' => 'required|in:daily,2_days,weekly',
            'date_type' => 'nullable|in:today,next_day',
            'use_feedback' => 'nullable|boolean',
        ]);


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
            $planType = $request->plan_type;
            $dateType = $request->input('date_type', 'today'); // 'today' or 'next_day'

            // Route to appropriate generation method
            if ($dateType === 'next_day') {
                return $this->generateNextDayPlan($user, $healthData, $planType);
            } else {
                return $this->generateTodayPlan($user, $healthData, $planType, $request);
            }
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate meal plan: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Generate meal plan for today.
     *
     * @param \App\Models\User $user
     * @param array $healthData
     * @param string $planType
     * @param Request $request
     * @return JsonResponse
     */
    protected function generateTodayPlan($user, array $healthData, string $planType, Request $request): JsonResponse
    {
        $useFeedback = $request->boolean('use_feedback', false);

        // Get previous feedback if requested
        $previousFeedback = null;
        if ($useFeedback) {
            $previousFeedback = $user->feedbacks()
                ->where('used_for_next_plan', false)
                ->latest('feedback_date')
                ->first();
        }

        // Generate meal plan using Llama
        if ($previousFeedback) {
            $llamaResponse = $this->llamaService->generateMealPlanWithFeedback(
                $healthData,
                $previousFeedback->toArray(),
                $planType
            );
        } else {
            $llamaResponse = $this->llamaService->generateMealPlan($healthData, $planType);
        }

        // Parse and save meal plan
        $planData = $this->responseHandler->parseMealPlanResponse($llamaResponse['response']);
        
        // Calculate dates
        $startDate = now()->toDateString();
        $endDate = match($planType) {
            'daily' => $startDate,
            '2_days' => now()->addDay()->toDateString(),
            'weekly' => now()->addDays(6)->toDateString(),
            default => $startDate,
        };

        // Create meal plan
        $mealPlan = MealPlan::create([
            'user_id' => $user->id,
            'plan_type' => $planType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'fitness_goal' => [$healthData['fitness_plan'] ?? null],
            'target_calories' => $this->calculateTargetCalories($healthData),
            'plan_data' => json_encode($planData),
            'llama_prompt' => $llamaResponse['prompt'],
            'llama_response' => $llamaResponse['response'],
            'is_active' => true,
        ]);

        // Create meals from parsed data
        $this->createMealsFromPlan($mealPlan, $planData);

        // Mark feedback as used if applicable
        if ($previousFeedback) {
            $previousFeedback->update(['used_for_next_plan' => true]);
        }

        $mealPlan->load('meals');

        // Format response for better API structure
        $formattedResponse = $this->responseHandler->formatForApiResponse($planData, $planType);

        return $this->successResponse([
            'meal_plan' => new MealPlanResource($mealPlan),
            'formatted_plan' => $formattedResponse,
            'date_type' => 'today',
            'llama_info' => [
                'model' => $llamaResponse['model'] ?? null,
                'parsing_method' => $planData['parsing_method'] ?? 'unknown',
            ],
        ], 'Meal plan generated successfully', 201);
    }

    /**
     * Generate meal plan for next day based on yesterday's consumption.
     *
     * @param \App\Models\User $user
     * @param array $healthData
     * @param string $planType
     * @return JsonResponse
     */
    protected function generateNextDayPlan($user, array $healthData, string $planType): JsonResponse
    {
        // Get yesterday's date
        $yesterday = now()->subDay()->toDateString();
        
        // Get ALL trackings from yesterday (not just 'ate')
        $yesterdayTrackings = $user->mealTrackings()
            ->where('tracking_date', $yesterday)
            ->with('meal')
            ->get();

        // Get yesterday's active meal plan to compare
        $yesterdayPlan = $user->mealPlans()
            ->where('start_date', '<=', $yesterday)
            ->where(function($query) use ($yesterday) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $yesterday);
            })
            ->where('is_active', true)
            ->with(['meals' => function($query) use ($yesterday) {
                $query->where('meal_date', $yesterday);
            }])
            ->latest()
            ->first();

        // Calculate target calories
        $targetCalories = $yesterdayPlan ? $yesterdayPlan->target_calories : $this->calculateTargetCalories($healthData);

        // Build comprehensive consumption analysis
        $yesterdayConsumption = [];
        $totalConsumedCalories = 0;
        $mealTypeBreakdown = [
            'breakfast' => ['ate' => 0, 'skipped' => 0, 'not_ate' => 0, 'calories' => 0],
            'lunch' => ['ate' => 0, 'skipped' => 0, 'not_ate' => 0, 'calories' => 0],
            'dinner' => ['ate' => 0, 'skipped' => 0, 'not_ate' => 0, 'calories' => 0],
            'snack' => ['ate' => 0, 'skipped' => 0, 'not_ate' => 0, 'calories' => 0],
        ];
        $timingPatterns = [];
        $skippedMeals = [];

        foreach ($yesterdayTrackings as $tracking) {
            $meal = $tracking->meal;
            $mealType = $meal->meal_type ?? 'breakfast';
            $status = $tracking->status ?? 'pending';
            $consumedCalories = $tracking->consumed_calories ?? $meal->calories ?? 0;
            
            // Track meal type breakdown
            if (isset($mealTypeBreakdown[$mealType])) {
                if ($status === 'ate') {
                    $mealTypeBreakdown[$mealType]['ate']++;
                    $mealTypeBreakdown[$mealType]['calories'] += $consumedCalories;
                    $totalConsumedCalories += $consumedCalories;
                } elseif ($status === 'skipped') {
                    $mealTypeBreakdown[$mealType]['skipped']++;
                    $skippedMeals[] = [
                        'meal_type' => $mealType,
                        'dish_name' => $meal->dish_name,
                        'suggested_time' => $meal->suggested_time,
                    ];
                } else {
                    $mealTypeBreakdown[$mealType]['not_ate']++;
                }
            }

            // Collect timing data
            if ($tracking->actual_time && $meal->suggested_time) {
                $suggestedTime = is_string($meal->suggested_time) ? $meal->suggested_time : $meal->suggested_time->format('H:i');
                $actualTime = $tracking->actual_time->format('H:i');
                $timingPatterns[] = [
                    'meal_type' => $mealType,
                    'suggested_time' => $suggestedTime,
                    'actual_time' => $actualTime,
                    'difference_minutes' => abs((strtotime($actualTime) - strtotime($suggestedTime)) / 60),
                ];
            }

            // Build consumption entry
            $yesterdayConsumption[] = [
                'meal_type' => $mealType,
                'dish_name' => $meal->dish_name,
                'calories' => $meal->calories,
                'consumed_calories' => $consumedCalories,
                'status' => $status,
                'modifications' => $tracking->modifications,
                'portion_size' => $tracking->portion_size,
                'suggested_time' => $meal->suggested_time,
                'actual_time' => $tracking->actual_time ? $tracking->actual_time->format('H:i') : null,
            ];
        }

        // Calculate calorie analysis
        $calorieDeficit = $targetCalories - $totalConsumedCalories;
        $caloriePercentage = $targetCalories > 0 ? round(($totalConsumedCalories / $targetCalories) * 100, 1) : 0;

        // Get previous feedback if available
        $previousFeedback = $user->feedbacks()
            ->where('feedback_date', $yesterday)
            ->first();

        // Build consumption summary for Llama
        $consumptionSummary = [
            'date' => $yesterday,
            'target_calories' => $targetCalories,
            'consumed_calories' => $totalConsumedCalories,
            'deficit' => $calorieDeficit,
            'surplus' => $calorieDeficit < 0 ? abs($calorieDeficit) : 0,
            'percentage_of_target' => $caloriePercentage,
            'meal_breakdown' => $mealTypeBreakdown,
            'skipped_meals' => $skippedMeals,
            'timing_patterns' => $timingPatterns,
            'consumption_details' => $yesterdayConsumption,
        ];

        // Generate next day meal plan with enhanced consumption data
        $llamaResponse = $this->llamaService->generateNextDayMealPlan(
            $healthData,
            $consumptionSummary,
            $previousFeedback ? $previousFeedback->toArray() : null,
            $planType
        );

        // Parse and save meal plan
        $planData = $this->responseHandler->parseMealPlanResponse($llamaResponse['response']);
        
        // Calculate dates for next day
        $startDate = now()->toDateString();
        $endDate = match($planType) {
            'daily' => $startDate,
            '2_days' => now()->addDay()->toDateString(),
            'weekly' => now()->addDays(6)->toDateString(),
            default => $startDate,
        };

        // Create meal plan
        $mealPlan = MealPlan::create([
            'user_id' => $user->id,
            'plan_type' => $planType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'fitness_goal' => [$healthData['fitness_plan'] ?? null],
            'target_calories' => $this->calculateTargetCalories($healthData),
            'plan_data' => json_encode($planData),
            'llama_prompt' => $llamaResponse['prompt'],
            'llama_response' => $llamaResponse['response'],
            'is_active' => true,
        ]);

        // Create meals from parsed data
        $this->createMealsFromPlan($mealPlan, $planData);

        $mealPlan->load('meals');

        // Format response
        $formattedResponse = $this->responseHandler->formatForApiResponse($planData, $planType);

        return $this->successResponse([
            'meal_plan' => new MealPlanResource($mealPlan),
            'formatted_plan' => $formattedResponse,
            'date_type' => 'next_day',
            'llama_info' => [
                'model' => $llamaResponse['model'] ?? null,
                'parsing_method' => $planData['parsing_method'] ?? 'unknown',
            ],
        ], 'Next day meal plan generated successfully', 201);
    }

    /**
     * Get user's meal plans.
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

            $query = $user->mealPlans()->with('meals');

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('plan_type')) {
                $query->where('plan_type', $request->plan_type);
            }

            $mealPlans = $query->latest()->paginate(10);

            return $this->successResponse([
                'data' => MealPlanResource::collection($mealPlans->items()),
                'current_page' => $mealPlans->currentPage(),
                'per_page' => $mealPlans->perPage(),
                'total' => $mealPlans->total(),
                'last_page' => $mealPlans->lastPage(),
            ], 'Meal plans retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve meal plans: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get a specific meal plan.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $mealPlan = $user->mealPlans()->with('meals')->findOrFail($id);

            return $this->successResponse(['meal_plan' => new MealPlanResource($mealPlan)], 'Meal plan retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve meal plan: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get current active meal plan.
     *
     * @return JsonResponse
     */
    public function current(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $mealPlan = $user->mealPlans()
                ->where('is_active', true)
                ->where('start_date', '<=', now()->toDateString())
                ->where(function($query) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', now()->toDateString());
                })
                ->with('meals')
                ->latest()
                ->first();

            if (!$mealPlan) {
                return $this->notFoundResponse('No active meal plan found');
            }

            return $this->successResponse(['meal_plan' => new MealPlanResource($mealPlan)], 'Current meal plan retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve current meal plan: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Select a meal option.
     * When user selects a meal, mark it as selected and unselect other options for the same meal time and date.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function selectMeal(int $id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $meal = Meal::whereHas('mealPlan', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })->findOrFail($id);

            // Unselect other options for the same meal type and date
            Meal::where('meal_plan_id', $meal->meal_plan_id)
                ->where('meal_type', $meal->meal_type)
                ->where('meal_date', $meal->meal_date)
                ->where('id', '!=', $meal->id)
                ->update(['is_selected' => false]);

            // Select this meal
            $meal->update(['is_selected' => true]);
            $meal->refresh();

            return $this->successResponse(['meal' => new MealResource($meal)], 'Meal selected successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to select meal: ' . $e->getMessage(), null, 500);
        }
    }



    /**
     * Create meals from parsed plan data.
     *
     * @param MealPlan $mealPlan
     * @param array $planData
     * @return void
     */
    protected function createMealsFromPlan(MealPlan $mealPlan, array $planData): void
    {
        if (!isset($planData['days']) || !is_array($planData['days'])) {
            return;
        }

        foreach ($planData['days'] as $day) {
            $mealDate = $day['date'] ?? now()->toDateString();
            
            if (!isset($day['meals']) || !is_array($day['meals'])) {
                continue;
            }

            foreach ($day['meals'] as $mealData) {
                $dishName = $mealData['dish_name'] ?? 'Meal';
                $mealType = $mealData['meal_type'] ?? 'breakfast';
                $description = $mealData['description'] ?? null;
                $imagePrompt = $mealData['image_prompt'] ?? null;
                
                // Generate real AI image for the meal using image_prompt if available, otherwise use dish_name and description
                if ($imagePrompt) {
                    // Use the detailed image_prompt from Llama
                    $imageUrl = $this->imageService->generateMealImageUrlFromPrompt($imagePrompt);
                } else {
                    // Fallback to generating from dish name and description
                    $imageUrl = $this->imageService->generateMealImageUrl($dishName, $description, $mealType);
                }
                
                Meal::create([
                    'meal_plan_id' => $mealPlan->id,
                    'meal_type' => $mealType,
                    'option_number' => $mealData['option_number'] ?? 1,
                    'is_selected' => false, // User will select later
                    'meal_date' => $mealDate,
                    'suggested_time' => $mealData['suggested_time'] ?? null,
                    'dish_name' => $dishName,
                    'image_url' => $imageUrl,
                    'description' => $mealData['description'] ?? null,
                    'ingredients' => $mealData['ingredients'] ?? [],
                    'food_preparation_materials' => $mealData['food_preparation_materials'] ?? [],
                    'bread_type' => $mealData['bread_type'] ?? null,
                    'rice_type' => $mealData['rice_type'] ?? null,
                    'sprouts_material' => $mealData['sprouts_material'] ?? null,
                    'calories' => $mealData['calories'] ?? null,
                    'protein' => $mealData['protein'] ?? null,
                    'carbs' => $mealData['carbs'] ?? null,
                    'fats' => $mealData['fats'] ?? null,
                    'cooking_instructions' => $mealData['cooking_instructions'] ?? null,
                    'calorie_instructions' => $mealData['calorie_instructions'] ?? null,
                ]);
            }
        }
    }

    /**
     * Calculate target calories based on health data.
     *
     * @param array $healthData
     * @return int
     */
    protected function calculateTargetCalories(array $healthData): int
    {
        // Basic BMR calculation (Mifflin-St Jeor Equation)
        $age = $healthData['age'] ?? 30;
        $weight = $healthData['weight'] ?? 70;
        $height = $healthData['height'] ?? 170;
        $gender = $healthData['gender'] ?? 'male';

        if ($gender === 'male') {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
        } else {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
        }

        // Activity multiplier (assuming moderate activity)
        $activityMultiplier = 1.55;
        $maintenanceCalories = $bmr * $activityMultiplier;

        // Adjust based on fitness goal
        $fitnessPlan = $healthData['fitness_plan'] ?? 'weight_loss';
        $adjustment = match($fitnessPlan) {
            'weight_loss' => -500, // Deficit
            'weight_gain' => 500,  // Surplus
            'muscle_building' => 300, // Slight surplus
            'fat_burning' => -300, // Moderate deficit
            default => 0,
        };

        return (int)($maintenanceCalories + $adjustment);
    }

    /**
     * Get home page data - Today's meal plan with tracking status and calorie summary.
     *
     * @return JsonResponse
     */
    public function home(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $today = now()->toDateString();

            // Get active meal plan for today
            $mealPlan = $user->mealPlans()
                ->where('is_active', true)
                ->where('start_date', '<=', $today)
                ->where(function($query) use ($today) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                })
                ->with(['meals' => function($query) use ($today) {
                    $query->where('meal_date', $today)
                        ->orderBy('suggested_time');
                }])
                ->latest()
                ->first();

            // Get today's meal trackings
            $trackings = $user->mealTrackings()
                ->where('tracking_date', $today)
                ->with('meal')
                ->get();

            // Calculate calorie summary
            $totalConsumedCalories = 0;
            $targetCalories = $mealPlan ? $mealPlan->target_calories : null;
            
            foreach ($trackings as $tracking) {
                if ($tracking->status === 'ate') {
                    $totalConsumedCalories += $tracking->consumed_calories ?? $tracking->meal->calories ?? 0;
                }
            }

            // Organize meals by type with tracking status
            $mealsByType = [
                'breakfast' => ['options' => [], 'selected' => null, 'tracking' => null],
                'lunch' => ['options' => [], 'selected' => null, 'tracking' => null],
                'dinner' => ['options' => [], 'selected' => null, 'tracking' => null],
                'snack' => ['options' => [], 'selected' => null, 'tracking' => null],
            ];

            if ($mealPlan) {
                foreach ($mealPlan->meals as $meal) {
                    $mealType = $meal->meal_type ?? 'breakfast';
                    if (isset($mealsByType[$mealType])) {
                        $mealsByType[$mealType]['options'][] = new MealResource($meal);
                        
                        // Find selected meal
                        if ($meal->is_selected) {
                            $mealsByType[$mealType]['selected'] = new MealResource($meal);
                            
                            // Find tracking for selected meal
                            $tracking = $trackings->firstWhere('meal_id', $meal->id);
                            if ($tracking) {
                                $mealsByType[$mealType]['tracking'] = new MealTrackingResource($tracking);
                            }
                        }
                    }
                }
            }

            // Get pending meals count
            $pendingMeals = $trackings->where('status', 'pending')->count();
            $ateMeals = $trackings->where('status', 'ate')->count();
            $skippedMeals = $trackings->where('status', 'skipped')->count();

            $homeData = [
                'date' => $today,
                'meal_plan' => $mealPlan ? new MealPlanResource($mealPlan) : null,
                'meals' => $mealsByType,
                'calorie_summary' => [
                    'consumed' => $totalConsumedCalories,
                    'target' => $targetCalories,
                    'remaining' => $targetCalories ? max(0, $targetCalories - $totalConsumedCalories) : null,
                    'percentage' => $targetCalories ? round(($totalConsumedCalories / $targetCalories) * 100, 1) : null,
                ],
                'meal_status' => [
                    'pending' => $pendingMeals,
                    'ate' => $ateMeals,
                    'skipped' => $skippedMeals,
                    'total' => $trackings->count(),
                ],
            ];

            return $this->successResponse($homeData, 'Home page data retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve home page data: ' . $e->getMessage(), null, 500);
        }
    }



    /**
     * Parse meal suggestions response from Llama.
     *
     * @param string $response
     * @return array
     */
    protected function parseMealSuggestionsResponse(string $response): array
    {
        // Try to parse as JSON first
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['days'])) {
            return $data;
        }

        // Fallback: Return basic structure
        return [
            'days' => [
                [
                    'date' => now()->toDateString(),
                    'total_calories' => 0,
                    'meals' => [],
                    'raw_response' => $response
                ]
            ]
        ];
    }
}
