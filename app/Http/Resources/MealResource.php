<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealResource extends JsonResource
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
            'meal_plan_id' => $this->meal_plan_id,
            'meal_type' => $this->meal_type,
            'option_number' => $this->option_number,
            'is_selected' => $this->is_selected,
            'meal_date' => $this->meal_date?->toDateString(),
            'suggested_time' => $this->suggested_time?->format('H:i'),
            'dish_name' => $this->dish_name,
            'image_url' => $this->image_url,
            'description' => $this->description,
            'ingredients' => $this->ingredients,
            'food_preparation_materials' => $this->food_preparation_materials,
            'bread_type' => $this->bread_type,
            'rice_type' => $this->rice_type,
            'sprouts_material' => $this->sprouts_material,
            'calories' => $this->calories,
            'protein' => $this->protein,
            'carbs' => $this->carbs,
            'fats' => $this->fats,
            'cooking_instructions' => $this->cooking_instructions,
            'calorie_instructions' => $this->calorie_instructions,
            'is_customizable' => $this->is_customizable,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
