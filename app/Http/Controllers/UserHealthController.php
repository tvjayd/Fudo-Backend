<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserHealthRequest;
use App\Http\Requests\UpdateUserHealthRequest;
use App\Models\UserHealthDetail;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserHealthController extends BaseController
{
    /**
     * Get the authenticated user's health details.
     *
     * @return JsonResponse
     */
    public function show(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->notFoundResponse('User not found');
            }

            $healthDetail = $user->healthDetail;

            if (!$healthDetail) {
                return $this->notFoundResponse('Health details not found');
            }

            return $this->successResponse(['health_detail' => $healthDetail], 'Health details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve health details: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store health details for the authenticated user.
     *
     * @param StoreUserHealthRequest $request
     * @return JsonResponse
     */
    public function store(StoreUserHealthRequest $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            // Check if health detail already exists
            if ($user->healthDetail) {
                return $this->errorResponse('Health details already exist. Use update endpoint instead.', null, 409);
            }

            $healthDetail = UserHealthDetail::create([
                'user_id' => $user->id,
                'age' => $request->age,
                'weight' => $request->weight,
                'height' => $request->height,
                'gender' => $request->gender,
                'fitness_plan' => $request->fitness_plan,
                'disease' => $request->disease,
                'lifestyle' => $request->lifestyle,
                'allergies' => $request->allergies,
                'workout_type' => $request->workout_type,
                'workout_intense_type' => $request->workout_intense_type,
                'workout_time' => $request->workout_time,
                'meal_type' => $request->meal_type,
                'type_of_test' => $request->type_of_test,
                'ingredients' => $request->ingredients,
                'ingredient_category' => $request->ingredient_category,
                'food_preparation_materials' => $request->food_preparation_materials,
                'bread_type' => $request->bread_type,
                'rice_type' => $request->rice_type,
                'sprouts_material' => $request->sprouts_material,
            ]);

            return $this->successResponse(['health_detail' => $healthDetail], 'Health details created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create health details: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update health details for the authenticated user.
     *
     * @param UpdateUserHealthRequest $request
     * @return JsonResponse
     */
    public function update(UpdateUserHealthRequest $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $healthDetail = $user->healthDetail;

            if (!$healthDetail) {
                // Create if doesn't exist
                $healthDetail = UserHealthDetail::create([
                    'user_id' => $user->id,
                    'age' => $request->age,
                    'weight' => $request->weight,
                    'height' => $request->height,
                    'gender' => $request->gender,
                    'fitness_plan' => $request->fitness_plan,
                    'disease' => $request->disease,
                    'lifestyle' => $request->lifestyle,
                    'allergies' => $request->allergies,
                    'workout_type' => $request->workout_type,
                    'workout_intense_type' => $request->workout_intense_type,
                    'workout_time' => $request->workout_time,
                    'meal_type' => $request->meal_type,
                    'type_of_test' => $request->type_of_test,
                    'ingredients' => $request->ingredients,
                    'ingredient_category' => $request->ingredient_category,
                    'food_preparation_materials' => $request->food_preparation_materials,
                    'bread_type' => $request->bread_type,
                    'rice_type' => $request->rice_type,
                    'sprouts_material' => $request->sprouts_material,
                ]);

                return $this->successResponse(['health_detail' => $healthDetail], 'Health details created successfully', 201);
            }

            // Update existing health detail
            $healthDetail->update([
                'age' => $request->age ?? $healthDetail->age,
                'weight' => $request->weight ?? $healthDetail->weight,
                'height' => $request->height ?? $healthDetail->height,
                'gender' => $request->gender ?? $healthDetail->gender,
                'fitness_plan' => $request->fitness_plan ?? $healthDetail->fitness_plan,
                'disease' => $request->disease ?? $healthDetail->disease,
                'lifestyle' => $request->lifestyle ?? $healthDetail->lifestyle,
                'allergies' => $request->allergies ?? $healthDetail->allergies,
                'workout_type' => $request->workout_type ?? $healthDetail->workout_type,
                'workout_intense_type' => $request->workout_intense_type ?? $healthDetail->workout_intense_type,
                'workout_time' => $request->workout_time ?? $healthDetail->workout_time,
                'meal_type' => $request->meal_type ?? $healthDetail->meal_type,
                'type_of_test' => $request->type_of_test ?? $healthDetail->type_of_test,
                'ingredients' => $request->ingredients ?? $healthDetail->ingredients,
                'ingredient_category' => $request->ingredient_category ?? $healthDetail->ingredient_category,
                'food_preparation_materials' => $request->food_preparation_materials ?? $healthDetail->food_preparation_materials,
                'bread_type' => $request->bread_type ?? $healthDetail->bread_type,
                'rice_type' => $request->rice_type ?? $healthDetail->rice_type,
                'sprouts_material' => $request->sprouts_material ?? $healthDetail->sprouts_material,
            ]);

            $healthDetail->refresh();

            return $this->successResponse(['health_detail' => $healthDetail], 'Health details updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update health details: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete health details for the authenticated user.
     *
     * @return JsonResponse
     */
    public function destroy(): JsonResponse
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

            $healthDetail->delete();

            return $this->successResponse(null, 'Health details deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete health details: ' . $e->getMessage(), null, 500);
        }
    }
}
