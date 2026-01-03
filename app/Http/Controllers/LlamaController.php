<?php

namespace App\Http\Controllers;

use App\Services\LlamaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class LlamaController extends BaseController
{
    protected LlamaService $llamaService;

    public function __construct(LlamaService $llamaService)
    {
        $this->llamaService = $llamaService;
    }

    /**
     * Check if Llama service is available.
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        try {
            $isAvailable = $this->llamaService->isAvailable();
            
            if ($isAvailable) {
                $models = $this->llamaService->listModels();
                return $this->successResponse([
                    'available' => true,
                    'models' => $models,
                ], 'Llama service is available');
            }

            return $this->errorResponse('Llama service is not available. Please ensure Ollama or compatible server is running.', null, 503);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to check Llama service status: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Unified endpoint for all Llama AI generation tasks.
     * Supports: health_recommendations, meal_plan, meal_plan_next_day, chat, text
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:health_recommendations,meal_plan',
            'data' => 'nullable|array',
            'options' => 'nullable|array',
            'options.temperature' => 'nullable|numeric|min:0|max:2',
            'options.top_p' => 'nullable|numeric|min:0|max:1',
            'options.max_tokens' => 'nullable|integer|min:1|max:4096',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $action = $request->input('action');
            $data = $request->input('data', []);
            $options = $request->input('options', []);

            // Route to appropriate handler based on action
            return match($action) {
                'health_recommendations' => $this->handleHealthRecommendations($options),
                'meal_plan' => $this->handleMealPlan($data, $options),
                default => $this->errorResponse('Invalid action specified', null, 400),
            };
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Unauthorized');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Handle health recommendations action.
     *
     * @param array $options
     * @return JsonResponse
     */
    protected function handleHealthRecommendations(array $options = []): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        
        if (!$user) {
            return $this->unauthorizedResponse('User not found');
        }

        $healthDetail = $user->healthDetail;

        if (!$healthDetail) {
            return $this->notFoundResponse('Health details not found. Please update your health profile first.');
        }

        $healthData = $healthDetail->toArray();
        $recommendation = $this->llamaService->generateHealthRecommendation($healthData);

        return $this->successResponse([
            'action' => 'health_recommendations',
            'response' => json_decode($recommendation),
            'model' => config('llama.model', 'llama3'),
            'health_data' => [
                'age' => $healthData['age'] ?? null,
                'weight' => $healthData['weight'] ?? null,
                'height' => $healthData['height'] ?? null,
                'fitness_plan' => $healthData['fitness_plan'] ?? null,
            ],
        ], 'Health recommendations generated successfully');
    }

    /**
     * Handle meal plan generation action.
     *
     * @param array $data
     * @param array $options
     * @return JsonResponse
     */
    protected function handleMealPlan(array $data, array $options = []): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        
        if (!$user) {
            return $this->unauthorizedResponse('User not found');
        }

        $healthDetail = $user->healthDetail;
        if (!$healthDetail) {
            return $this->notFoundResponse('Health details not found. Please update your health profile first.');
        }

        $planType = $data['plan_type'] ?? 'daily';
        $useFeedback = $data['use_feedback'] ?? false;

        $healthData = $healthDetail->toArray();

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
            $llamaResponse = $this->llamaService->generateMealPlan($healthData, $planType, $options);
        }

        return $this->successResponse([
            'action' => 'meal_plan',
            'response' => json_encode($llamaResponse['response']) ?? '',
            'model' => $llamaResponse['model'] ?? config('llama.model', 'llama3'),
            'plan_type' => $planType,
            'metadata' => [
                'used_feedback' => $useFeedback && $previousFeedback !== null,
            ],
        ], 'Meal plan generated successfully');
    }


    /**
     * List available models.
     *
     * @return JsonResponse
     */
    public function models(): JsonResponse
    {
        try {
            $models = $this->llamaService->listModels();
            return $this->successResponse(['models' => $models], 'Models retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to list models: ' . $e->getMessage(), null, 500);
        }
    }
}
