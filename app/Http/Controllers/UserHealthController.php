<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserHealthRequest;
use App\Http\Requests\UpdateUserHealthRequest;
use App\Http\Resources\UserHealthDetailResource;
use App\Models\UserHealthDetail;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

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

            return $this->successResponse(['health_detail' => new UserHealthDetailResource($healthDetail)], 'Health details retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
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

            return $this->successResponse(['health_detail' => new UserHealthDetailResource($healthDetail)], 'Health details created successfully', 201);
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
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

                return $this->successResponse(['health_detail' => new UserHealthDetailResource($healthDetail)], 'Health details created successfully', 201);
            }

            // Update existing health detail - only update fields that are provided
            $updateData = [];
            $fields = [
                'age', 'weight', 'height', 'gender', 'fitness_plan', 'disease', 
                'lifestyle', 'allergies', 'workout_type', 'workout_intense_type', 
                'workout_time', 'meal_type', 'type_of_test', 'ingredients', 
                'ingredient_category', 'food_preparation_materials', 'bread_type', 
                'rice_type', 'sprouts_material'
            ];
            
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                }
            }
            
            $healthDetail->update($updateData);

            $healthDetail->refresh();

            return $this->successResponse(['health_detail' => new UserHealthDetailResource($healthDetail)], 'Health details updated successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
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
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete health details: ' . $e->getMessage(), null, 500);
        }
    }
}
