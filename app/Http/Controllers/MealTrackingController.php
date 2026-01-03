<?php

namespace App\Http\Controllers;

use App\Http\Resources\MealResource;
use App\Http\Resources\MealTrackingResource;
use App\Models\Meal;
use App\Models\MealTracking;
use App\Services\MealImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class MealTrackingController extends BaseController
{
    protected MealImageService $imageService;

    public function __construct(MealImageService $imageService)
    {
        $this->imageService = $imageService;
    }
    /**
     * Get meal reminders for today.
     *
     * @return JsonResponse
     */
    public function reminders(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $today = now()->toDateString();
            
            // Get active meal plan
            $mealPlan = $user->mealPlans()
                ->where('is_active', true)
                ->where('start_date', '<=', $today)
                ->where(function($query) use ($today) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                })
                ->latest()
                ->first();

            if (!$mealPlan) {
                return $this->notFoundResponse('No active meal plan found');
            }

            // Get meals for today
            $meals = $mealPlan->meals()
                ->where('meal_date', $today)
                ->orderBy('suggested_time')
                ->get();

            // Get or create trackings
            $reminders = [];
            foreach ($meals as $meal) {
                $tracking = MealTracking::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'meal_id' => $meal->id,
                        'tracking_date' => $today,
                    ],
                    [
                        'reminder_time' => $meal->suggested_time,
                        'status' => 'pending',
                    ]
                );

                $tracking->load('meal');
                $reminders[] = [
                    'tracking' => new MealTrackingResource($tracking),
                    'meal' => new MealResource($meal),
                ];
            }

            return $this->successResponse(['reminders' => $reminders], 'Meal reminders retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve reminders: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update meal tracking status.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:ate,not_ate,to_be_had,skipped',
            'actual_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
            'consumed_calories' => 'nullable|integer|min:0',
            'portion_size' => 'nullable|in:full,half,double,quarter,three_quarters',
            'modifications' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $tracking = $user->mealTrackings()->with('meal')->findOrFail($id);

            $updateData = [
                'status' => $request->status,
            ];

            if ($request->has('actual_time')) {
                $updateData['actual_time'] = $request->actual_time;
            }

            if ($request->has('notes')) {
                $updateData['notes'] = $request->notes;
            }

            // If status is "ate", store consumption details
            if ($request->status === 'ate') {
                $meal = $tracking->meal;
                
                // Calculate consumed calories if portion size is different
                $consumedCalories = $request->input('consumed_calories');
                if (!$consumedCalories && $request->has('portion_size')) {
                    $baseCalories = $meal->calories ?? 0;
                    $portionMultiplier = match($request->portion_size) {
                        'half' => 0.5,
                        'quarter' => 0.25,
                        'three_quarters' => 0.75,
                        'double' => 2.0,
                        default => 1.0,
                    };
                    $consumedCalories = (int)($baseCalories * $portionMultiplier);
                } elseif (!$consumedCalories) {
                    $consumedCalories = $meal->calories ?? null;
                }
                
                $updateData['consumed_calories'] = $consumedCalories;
                
                if ($request->has('portion_size')) {
                    $updateData['portion_size'] = $request->portion_size;
                }
                
                if ($request->has('modifications')) {
                    $updateData['modifications'] = $request->modifications;
                }
                
                // Generate consumption image URL
                $dishName = $meal->dish_name ?? 'Meal';
                $updateData['consumption_image_url'] = $this->imageService->generateConsumptionImageUrl($dishName);
            }

            $tracking->update($updateData);
            $tracking->refresh();
            $tracking->load('meal');

            return $this->successResponse(['tracking' => new MealTrackingResource($tracking)], 'Meal tracking updated successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update tracking: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Mark pending meals before sleep.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function markBeforeSleep(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'trackings' => 'required|array',
            'trackings.*.id' => 'required|exists:meal_trackings,id',
            'trackings.*.status' => 'required|in:ate,not_ate,to_be_had,skipped',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $date = $request->date ?? now()->toDateString();
            $trackings = $request->trackings;

            $updated = [];
            foreach ($trackings as $trackingData) {
                $tracking = $user->mealTrackings()
                    ->where('id', $trackingData['id'])
                    ->where('tracking_date', $date)
                    ->first();

                if ($tracking) {
                    $tracking->update([
                        'status' => $trackingData['status'],
                        'marked_before_sleep' => true,
                        'marked_at' => now(),
                    ]);
                    $tracking->load('meal');
                    $updated[] = $tracking;
                }
            }

            return $this->successResponse([
                'trackings' => MealTrackingResource::collection($updated)
            ], 'Meals marked before sleep successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to mark meals: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get tracking history.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $query = $user->mealTrackings()->with('meal');

            if ($request->has('date')) {
                $query->where('tracking_date', $request->date);
            } else {
                $query->where('tracking_date', '>=', now()->subDays(7)->toDateString());
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $trackings = $query->orderBy('tracking_date', 'desc')
                ->orderBy('reminder_time')
                ->paginate(20);

            return $this->successResponse([
                'data' => MealTrackingResource::collection($trackings->items()),
                'current_page' => $trackings->currentPage(),
                'per_page' => $trackings->perPage(),
                'total' => $trackings->total(),
                'last_page' => $trackings->lastPage(),
            ], 'Tracking history retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve history: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get pending meals for a specific date.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $date = $request->date ?? now()->toDateString();

            $pending = $user->mealTrackings()
                ->where('tracking_date', $date)
                ->where('status', 'pending')
                ->with('meal')
                ->orderBy('reminder_time')
                ->get();

            return $this->successResponse([
                'pending_meals' => MealTrackingResource::collection($pending)
            ], 'Pending meals retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve pending meals: ' . $e->getMessage(), null, 500);
        }
    }
}
