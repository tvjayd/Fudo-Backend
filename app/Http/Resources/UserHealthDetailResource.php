<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserHealthDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'age' => $this->age,
            'weight' => $this->weight,
            'height' => $this->height,
            'gender' => $this->gender,
            'fitness_plan' => $this->fitness_plan,
            'disease' => $this->disease,
            'lifestyle' => $this->lifestyle,
            'allergies' => $this->allergies,
            'workout_type' => $this->workout_type,
            'workout_intense_type' => $this->workout_intense_type,
            'workout_time' => $this->workout_time,
            'meal_type' => $this->meal_type,
            'type_of_test' => $this->type_of_test,
            'ingredients' => $this->ingredients,
            'ingredient_category' => $this->ingredient_category,
            'food_preparation_materials' => $this->food_preparation_materials,
            'bread_type' => $this->bread_type,
            'rice_type' => $this->rice_type,
            'sprouts_material' => $this->sprouts_material,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
