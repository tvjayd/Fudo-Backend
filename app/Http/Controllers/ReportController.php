<?php

namespace App\Http\Controllers;

use App\Models\MealTracking;
use App\Models\UserFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class ReportController extends BaseController
{
    /**
     * Get daily report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function daily(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $date = $request->input('date', now()->toDateString());
            $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));

            // Get meal trackings for the date
            $trackings = $user->mealTrackings()
                ->where('tracking_date', $date)
                ->with('meal')
                ->get();

            // Calculate statistics
            $totalCalories = 0;
            $mealsByType = [
                'breakfast' => ['count' => 0, 'calories' => 0, 'ate' => 0],
                'lunch' => ['count' => 0, 'calories' => 0, 'ate' => 0],
                'dinner' => ['count' => 0, 'calories' => 0, 'ate' => 0],
                'snack' => ['count' => 0, 'calories' => 0, 'ate' => 0],
            ];

            foreach ($trackings as $tracking) {
                $meal = $tracking->meal;
                $mealType = $meal->meal_type ?? 'breakfast';
                $calories = $tracking->consumed_calories ?? $meal->calories ?? 0;

                if (isset($mealsByType[$mealType])) {
                    $mealsByType[$mealType]['count']++;
                    $mealsByType[$mealType]['calories'] += $calories;
                    if ($tracking->status === 'ate') {
                        $mealsByType[$mealType]['ate']++;
                        $totalCalories += $calories;
                    }
                }
            }

            // Get target calories from active meal plan
            $targetCalories = null;
            $activePlan = $user->mealPlans()
                ->where('is_active', true)
                ->where('start_date', '<=', $date)
                ->where(function($query) use ($date) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $date);
                })
                ->first();
            
            if ($activePlan) {
                $targetCalories = $activePlan->target_calories;
            }

            // Get yesterday's summary
            $yesterdayTrackings = $user->mealTrackings()
                ->where('tracking_date', $yesterday)
                ->where('status', 'ate')
                ->with('meal')
                ->get();

            $yesterdayCalories = 0;
            foreach ($yesterdayTrackings as $tracking) {
                $yesterdayCalories += $tracking->consumed_calories ?? $tracking->meal->calories ?? 0;
            }

            // Get today's planned meals
            $todayMeals = [];
            if ($activePlan) {
                $todayMeals = $activePlan->meals()
                    ->where('meal_date', $date)
                    ->where('is_selected', true)
                    ->get();
            }

            // Get feedback for the date if available
            $feedback = $user->feedbacks()
                ->where('feedback_date', $date)
                ->first();

            $report = [
                'date' => $date,
                'summary' => [
                    'total_calories_consumed' => $totalCalories,
                    'target_calories' => $targetCalories,
                    'calories_remaining' => $targetCalories ? ($targetCalories - $totalCalories) : null,
                    'calories_percentage' => $targetCalories ? round(($totalCalories / $targetCalories) * 100, 1) : null,
                ],
                'meal_breakdown' => $mealsByType,
                'yesterday_summary' => [
                    'date' => $yesterday,
                    'total_calories' => $yesterdayCalories,
                    'meals_consumed' => $yesterdayTrackings->count(),
                ],
                'today_planned_meals' => $todayMeals,
                'feedback' => $feedback,
            ];

            return $this->successResponse(['report' => $report], 'Daily report retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate daily report: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get weekly report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function weekly(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $startDate = $request->input('start_date', now()->startOfWeek()->toDateString());
            $endDate = date('Y-m-d', strtotime($startDate . ' +6 days'));

            // Get all trackings for the week
            $trackings = $user->mealTrackings()
                ->whereBetween('tracking_date', [$startDate, $endDate])
                ->where('status', 'ate')
                ->with('meal')
                ->get();

            // Calculate weekly statistics
            $dailyStats = [];
            $totalCalories = 0;
            $totalMeals = 0;
            $averageCalories = 0;

            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime($startDate . " +{$i} days"));
                $dayTrackings = $trackings->where('tracking_date', $date);
                
                $dayCalories = 0;
                foreach ($dayTrackings as $tracking) {
                    $dayCalories += $tracking->consumed_calories ?? $tracking->meal->calories ?? 0;
                }
                
                $dailyStats[] = [
                    'date' => $date,
                    'calories' => $dayCalories,
                    'meals_count' => $dayTrackings->count(),
                ];
                
                $totalCalories += $dayCalories;
                $totalMeals += $dayTrackings->count();
            }

            $averageCalories = $totalCalories / 7;

            // Get target calories
            $targetCalories = null;
            $activePlan = $user->mealPlans()
                ->where('is_active', true)
                ->where('start_date', '<=', $endDate)
                ->where(function($query) use ($startDate) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $startDate);
                })
                ->first();
            
            if ($activePlan) {
                $targetCalories = $activePlan->target_calories;
            }

            $weeklyTarget = $targetCalories ? ($targetCalories * 7) : null;

            $report = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'summary' => [
                    'total_calories' => $totalCalories,
                    'weekly_target' => $weeklyTarget,
                    'average_daily_calories' => round($averageCalories, 1),
                    'total_meals' => $totalMeals,
                    'average_meals_per_day' => round($totalMeals / 7, 1),
                ],
                'daily_breakdown' => $dailyStats,
            ];

            return $this->successResponse(['report' => $report], 'Weekly report retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate weekly report: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get progress report toward fitness goal.
     *
     * @return JsonResponse
     */
    public function progress(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $healthDetail = $user->healthDetail;
            if (!$healthDetail) {
                return $this->notFoundResponse('Health details not found');
            }

            $fitnessGoal = $healthDetail->fitness_plan ?? 'weight_loss';
            $targetWeight = null; // Could be added to health details in future

            // Get last 30 days of consumption
            $startDate = now()->subDays(30)->toDateString();
            $endDate = now()->toDateString();

            $trackings = $user->mealTrackings()
                ->whereBetween('tracking_date', [$startDate, $endDate])
                ->where('status', 'ate')
                ->with('meal')
                ->get();

            $totalCalories = 0;
            $daysWithMeals = [];
            foreach ($trackings as $tracking) {
                $date = $tracking->tracking_date->toDateString();
                if (!isset($daysWithMeals[$date])) {
                    $daysWithMeals[$date] = 0;
                }
                $daysWithMeals[$date]++;
                $totalCalories += $tracking->consumed_calories ?? $tracking->meal->calories ?? 0;
            }

            $averageDailyCalories = count($daysWithMeals) > 0 ? ($totalCalories / count($daysWithMeals)) : 0;
            $complianceRate = (count($daysWithMeals) / 30) * 100;

            // Get active meal plan target
            $activePlan = $user->mealPlans()
                ->where('is_active', true)
                ->first();
            
            $targetCalories = $activePlan->target_calories ?? null;

            $report = [
                'fitness_goal' => $fitnessGoal,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => 30,
                ],
                'statistics' => [
                    'total_calories_consumed' => $totalCalories,
                    'average_daily_calories' => round($averageDailyCalories, 1),
                    'target_daily_calories' => $targetCalories,
                    'days_with_meals' => count($daysWithMeals),
                    'compliance_rate' => round($complianceRate, 1),
                ],
                'recommendations' => $this->generateRecommendations($fitnessGoal, $averageDailyCalories, $targetCalories),
            ];

            return $this->successResponse(['report' => $report], 'Progress report retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate progress report: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Generate recommendations based on progress.
     *
     * @param string $fitnessGoal
     * @param float $averageCalories
     * @param int|null $targetCalories
     * @return array
     */
    protected function generateRecommendations(string $fitnessGoal, float $averageCalories, ?int $targetCalories): array
    {
        $recommendations = [];

        if (!$targetCalories) {
            return ['Set up a meal plan to track progress toward your fitness goal.'];
        }

        $difference = $averageCalories - $targetCalories;
        $percentage = abs(($difference / $targetCalories) * 100);

        switch ($fitnessGoal) {
            case 'weight_loss':
                if ($difference > 100) {
                    $recommendations[] = "You're consuming {$percentage}% more calories than your target. Consider reducing portion sizes or choosing lower-calorie options.";
                } elseif ($difference < -100) {
                    $recommendations[] = "You're consuming {$percentage}% fewer calories than your target. Make sure you're eating enough to maintain energy levels.";
                } else {
                    $recommendations[] = "Great job! You're staying close to your calorie target for weight loss.";
                }
                break;

            case 'weight_gain':
                if ($difference < -100) {
                    $recommendations[] = "You're consuming {$percentage}% fewer calories than your target. Increase portion sizes or add more calorie-dense foods.";
                } elseif ($difference > 100) {
                    $recommendations[] = "You're exceeding your target. Consider adjusting your meal plan if weight gain is too rapid.";
                } else {
                    $recommendations[] = "Good progress! You're maintaining your calorie target for weight gain.";
                }
                break;

            case 'muscle_building':
                if ($difference < -100) {
                    $recommendations[] = "You need more calories for muscle building. Increase protein intake and overall calories.";
                } else {
                    $recommendations[] = "Maintain your current calorie intake and focus on protein-rich meals for muscle building.";
                }
                break;

            case 'fat_burning':
                if ($difference > 100) {
                    $recommendations[] = "Reduce calorie intake slightly to optimize fat burning while maintaining muscle mass.";
                } else {
                    $recommendations[] = "You're on track for fat burning. Continue with your current meal plan.";
                }
                break;
        }

        return $recommendations;
    }

    /**
     * Get user's calorie usage for today or specific date.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calorieUsage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $date = $request->input('date', now()->toDateString());

            // Get meal trackings for the date
            $trackings = $user->mealTrackings()
                ->where('tracking_date', $date)
                ->where('status', 'ate')
                ->with('meal')
                ->get();

            // Calculate total calories consumed
            $totalCalories = 0;
            $mealsByType = [
                'breakfast' => ['calories' => 0, 'meals' => []],
                'lunch' => ['calories' => 0, 'meals' => []],
                'dinner' => ['calories' => 0, 'meals' => []],
                'snack' => ['calories' => 0, 'meals' => []],
            ];

            foreach ($trackings as $tracking) {
                $meal = $tracking->meal;
                $mealType = $meal->meal_type ?? 'breakfast';
                $calories = $tracking->consumed_calories ?? $meal->calories ?? 0;
                
                if (isset($mealsByType[$mealType])) {
                    $mealsByType[$mealType]['calories'] += $calories;
                    $mealsByType[$mealType]['meals'][] = [
                        'dish_name' => $meal->dish_name,
                        'calories' => $calories,
                        'consumed_at' => $tracking->actual_time?->format('H:i'),
                    ];
                }
                
                $totalCalories += $calories;
            }

            // Get target calories from active meal plan
            $targetCalories = null;
            $activePlan = $user->mealPlans()
                ->where('is_active', true)
                ->where('start_date', '<=', $date)
                ->where(function($query) use ($date) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $date);
                })
                ->first();
            
            if ($activePlan) {
                $targetCalories = $activePlan->target_calories;
            }

            $usage = [
                'date' => $date,
                'total_calories_consumed' => $totalCalories,
                'target_calories' => $targetCalories,
                'calories_remaining' => $targetCalories ? max(0, $targetCalories - $totalCalories) : null,
                'calories_percentage' => $targetCalories ? round(($totalCalories / $targetCalories) * 100, 1) : null,
                'breakdown_by_meal' => $mealsByType,
                'total_meals_consumed' => $trackings->count(),
            ];

            return $this->successResponse($usage, 'Calorie usage retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve calorie usage: ' . $e->getMessage(), null, 500);
        }
    }
}

