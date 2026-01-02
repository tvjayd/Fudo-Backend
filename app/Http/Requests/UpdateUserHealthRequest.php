<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserHealthRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Basic Information
            'age' => 'nullable|integer|min:1|max:150',
            'weight' => 'nullable|numeric|min:0|max:500',
            'height' => 'nullable|numeric|min:0|max:300',
            'gender' => 'nullable|in:male,female,other',
            
            // Fitness Plan
            'fitness_plan' => 'nullable|in:weight_loss,weight_gain,muscle_building,fat_burning',
            
            // Health Information
            'disease' => 'nullable|string|max:1000',
            'lifestyle' => 'nullable|string|max:500',
            'allergies' => 'nullable|string|max:1000',
            
            // Workout Information
            'workout_type' => 'nullable|in:gym,indoor,calisthenics,outdoor,gymnastic',
            'workout_intense_type' => 'nullable|string|max:255',
            'workout_time' => 'nullable|string|max:255',
            
            // Meal Information
            'meal_type' => 'nullable|in:veg,non_veg,vegan',
            'type_of_test' => 'nullable|string|max:255',
            
            // Ingredients
            'ingredients' => 'nullable|array',
            'ingredients.*' => 'string|max:255',
            'ingredient_category' => 'nullable|in:veggies,mass',
            
            // Food Preparation
            'food_preparation_materials' => 'nullable|array',
            'food_preparation_materials.*' => 'string|max:255',
            'bread_type' => 'nullable|string|max:255',
            'rice_type' => 'nullable|string|max:255',
            'sprouts_material' => 'nullable|array',
            'sprouts_material.*' => 'string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gender.in' => 'Gender must be one of: male, female, other',
            'fitness_plan.in' => 'Fitness plan must be one of: weight_loss, weight_gain, muscle_building, fat_burning',
            'workout_type.in' => 'Workout type must be one of: gym, indoor, calisthenics, outdoor, gymnastic',
            'meal_type.in' => 'Meal type must be one of: veg, non_veg, vegan',
            'ingredient_category.in' => 'Ingredient category must be one of: veggies, mass',
        ];
    }
}
