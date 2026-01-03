<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Http\Resources\UserHealthDetailResource;
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

           
            $token = JWTAuth::fromUser($user);

            $responseData = [
                'user' => $user,
                'token' => $token,
                'token_type' => 'bearer'
            ];

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

        $user = auth('api')->user();

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
            'health' => $user->healthDetail,
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

