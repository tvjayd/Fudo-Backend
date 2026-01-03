<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserFeedbackResource;
use App\Models\UserFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class UserFeedbackController extends BaseController
{
    /**
     * Submit user feedback for a day.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'feedback_date' => 'required|date',
            'meal_feedbacks' => 'nullable|array',
            'meal_feedbacks.*.meal_id' => 'required|exists:meals,id',
            'meal_feedbacks.*.rating' => 'nullable|integer|min:1|max:5',
            'meal_feedbacks.*.liked' => 'nullable|boolean',
            'meal_feedbacks.*.comments' => 'nullable|string|max:500',
            'overall_satisfaction' => 'nullable|integer|min:1|max:5',
            'liked_meals' => 'nullable|string|max:1000',
            'disliked_meals' => 'nullable|string|max:1000',
            'suggestions' => 'nullable|string|max:1000',
            'hunger_level_met' => 'nullable|boolean',
            'energy_level' => 'nullable|integer|min:1|max:5',
            'additional_notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            // Check if feedback already exists for this date
            $existingFeedback = $user->feedbacks()
                ->where('feedback_date', $request->feedback_date)
                ->first();

            if ($existingFeedback) {
                // Update existing feedback
                $existingFeedback->update($request->only([
                    'meal_feedbacks',
                    'overall_satisfaction',
                    'liked_meals',
                    'disliked_meals',
                    'suggestions',
                    'hunger_level_met',
                    'energy_level',
                    'additional_notes',
                ]));

                $existingFeedback->load(['mealPlan', 'meal']);
                return $this->successResponse(['feedback' => new UserFeedbackResource($existingFeedback)], 'Feedback updated successfully');
            }

            // Create new feedback
            $feedback = UserFeedback::create([
                'user_id' => $user->id,
                'feedback_date' => $request->feedback_date,
                'meal_feedbacks' => $request->meal_feedbacks ?? [],
                'overall_satisfaction' => $request->overall_satisfaction,
                'liked_meals' => $request->liked_meals,
                'disliked_meals' => $request->disliked_meals,
                'suggestions' => $request->suggestions,
                'hunger_level_met' => $request->hunger_level_met,
                'energy_level' => $request->energy_level,
                'additional_notes' => $request->additional_notes,
            ]);

            $feedback->load(['mealPlan', 'meal']);
            return $this->successResponse(['feedback' => new UserFeedbackResource($feedback)], 'Feedback submitted successfully', 201);
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to submit feedback: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get feedback history.
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

            $query = $user->feedbacks();

            if ($request->has('date')) {
                $query->where('feedback_date', $request->date);
            } else {
                $query->where('feedback_date', '>=', now()->subDays(30)->toDateString());
            }

            if ($request->has('used_for_next_plan')) {
                $query->where('used_for_next_plan', $request->boolean('used_for_next_plan'));
            }

            $feedbacks = $query->with(['mealPlan', 'meal'])->orderBy('feedback_date', 'desc')->paginate(20);

            return $this->successResponse([
                'data' => UserFeedbackResource::collection($feedbacks->items()),
                'current_page' => $feedbacks->currentPage(),
                'per_page' => $feedbacks->perPage(),
                'total' => $feedbacks->total(),
                'last_page' => $feedbacks->lastPage(),
            ], 'Feedback history retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve feedback: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get feedback for a specific date.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $feedback = $user->feedbacks()
                ->with(['mealPlan', 'meal'])
                ->where('feedback_date', $request->date)
                ->first();

            if (!$feedback) {
                return $this->notFoundResponse('Feedback not found for this date');
            }

            return $this->successResponse(['feedback' => new UserFeedbackResource($feedback)], 'Feedback retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve feedback: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Evaluate diet data and get summary.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function evaluate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $startDate = $request->start_date ?? now()->subDays(7)->toDateString();
            $endDate = $request->end_date ?? now()->toDateString();

            // Get feedbacks in date range
            $feedbacks = $user->feedbacks()
                ->whereBetween('feedback_date', [$startDate, $endDate])
                ->get();

            // Get tracking data
            $trackings = $user->mealTrackings()
                ->whereBetween('tracking_date', [$startDate, $endDate])
                ->with('meal')
                ->get();

            // Calculate statistics
            $totalMeals = $trackings->count();
            $ateMeals = $trackings->where('status', 'ate')->count();
            $skippedMeals = $trackings->where('status', 'skipped')->count();
            $notAteMeals = $trackings->where('status', 'not_ate')->count();

            $totalCalories = $trackings->where('status', 'ate')
                ->sum(function($tracking) {
                    return $tracking->meal->calories ?? 0;
                });

            $averageSatisfaction = $feedbacks->avg('overall_satisfaction');
            $averageEnergyLevel = $feedbacks->avg('energy_level');
            $hungerLevelMetCount = $feedbacks->where('hunger_level_met', true)->count();

            $evaluation = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'meal_statistics' => [
                    'total_meals' => $totalMeals,
                    'ate' => $ateMeals,
                    'skipped' => $skippedMeals,
                    'not_ate' => $notAteMeals,
                    'compliance_rate' => $totalMeals > 0 ? round(($ateMeals / $totalMeals) * 100, 2) : 0,
                ],
                'nutrition_statistics' => [
                    'total_calories_consumed' => $totalCalories,
                    'average_daily_calories' => $totalMeals > 0 ? round($totalCalories / max(1, (strtotime($endDate) - strtotime($startDate)) / 86400), 2) : 0,
                ],
                'satisfaction_statistics' => [
                    'average_satisfaction' => round($averageSatisfaction ?? 0, 2),
                    'average_energy_level' => round($averageEnergyLevel ?? 0, 2),
                    'hunger_level_met_percentage' => $feedbacks->count() > 0 ? round(($hungerLevelMetCount / $feedbacks->count()) * 100, 2) : 0,
                ],
                'recommendations' => $this->generateRecommendations($feedbacks, $trackings),
            ];

            return $this->successResponse(['evaluation' => $evaluation], 'Diet evaluation completed successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to evaluate diet: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Generate recommendations based on feedback and tracking.
     *
     * @param $feedbacks
     * @param $trackings
     * @return array
     */
    protected function generateRecommendations($feedbacks, $trackings): array
    {
        $recommendations = [];

        $complianceRate = $trackings->count() > 0 
            ? ($trackings->where('status', 'ate')->count() / $trackings->count()) * 100 
            : 0;

        if ($complianceRate < 70) {
            $recommendations[] = 'Your meal compliance rate is low. Try to follow the meal plan more consistently.';
        }

        $averageSatisfaction = $feedbacks->avg('overall_satisfaction');
        if ($averageSatisfaction && $averageSatisfaction < 3) {
            $recommendations[] = 'Consider providing feedback to improve your meal plan based on your preferences.';
        }

        $skippedCount = $trackings->where('status', 'skipped')->count();
        if ($skippedCount > $trackings->count() * 0.3) {
            $recommendations[] = 'You are skipping too many meals. Regular meals are important for your fitness goals.';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Great job! Keep following your meal plan consistently.';
        }

        return $recommendations;
    }
}
