<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserHealthDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends BaseController
{
    /**
     * Register a new user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            // Optional health data
            'age' => 'nullable|integer|min:1|max:150',
            'weight' => 'nullable|numeric|min:0|max:500',
            'height' => 'nullable|numeric|min:0|max:300',
            'gender' => 'nullable|in:male,female,other',
            'fitness_plan' => 'nullable|in:weight_loss,weight_gain,muscle_building,fat_burning',
            'disease' => 'nullable|string|max:1000',
            'lifestyle' => 'nullable|string|max:500',
            'allergies' => 'nullable|string|max:1000',
            'workout_type' => 'nullable|in:gym,indoor,calisthenics,outdoor,gymnastic',
            'workout_intense_type' => 'nullable|string|max:255',
            'workout_time' => 'nullable|string|max:255',
            'meal_type' => 'nullable|in:veg,non_veg,vegan',
            'type_of_test' => 'nullable|string|max:255',
            'ingredients' => 'nullable|array',
            'ingredients.*' => 'string|max:255',
            'ingredient_category' => 'nullable|in:veggies,mass',
            'food_preparation_materials' => 'nullable|array',
            'food_preparation_materials.*' => 'string|max:255',
            'bread_type' => 'nullable|string|max:255',
            'rice_type' => 'nullable|string|max:255',
            'sprouts_material' => 'nullable|array',
            'sprouts_material.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Create health details if provided
            $healthDetail = null;
            if ($request->hasAny(['age', 'weight', 'height', 'gender', 'fitness_plan', 'disease', 'lifestyle', 
                'allergies', 'workout_type', 'workout_intense_type', 'workout_time', 'meal_type', 
                'type_of_test', 'ingredients', 'ingredient_category', 'food_preparation_materials', 
                'bread_type', 'rice_type', 'sprouts_material'])) {
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
            }

            $token = JWTAuth::fromUser($user);

            $responseData = [
                'user' => $user,
                'token' => $token,
                'token_type' => 'bearer',
            ];

            if ($healthDetail) {
                $responseData['health_detail'] = $healthDetail;
            }

            return $this->successResponse($responseData, 'User registered successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Login user and create token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return $this->unauthorizedResponse('Invalid email or password');
            }
        } catch (JWTException $e) {
            return $this->errorResponse('Could not create token', null, 500);
        }

        $user = auth()->user();

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60, // in seconds
        ], 'Login successful');
    }

    /**
     * Get authenticated user.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->notFoundResponse('User not found');
            }

            // Load health detail relationship
            $user->load('healthDetail');

            return $this->successResponse(['user' => $user], 'User retrieved successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        }
    }

    /**
     * Logout user (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::parseToken()->invalidate();

            return $this->successResponse(null, 'Successfully logged out');
        } catch (JWTException $e) {
            return $this->errorResponse('Failed to logout, please try again', null, 500);
        }
    }

    /**
     * Refresh a token.
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::parseToken()->refresh();

            return $this->successResponse([
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60, // in seconds
            ], 'Token refreshed successfully');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unable to refresh token');
        }
    }
}

