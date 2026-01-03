<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRecipe extends Model
{
    protected $fillable = [
        'user_id',
        'recipe_name',
        'description',
        'ingredients',
        'food_preparation_materials',
        'bread_type',
        'rice_type',
        'sprouts_material',
        'calories',
        'protein',
        'carbs',
        'fats',
        'cooking_instructions',
        'calorie_instructions',
        'serving_size',
        'prep_time',
        'cook_time',
        'llama_prompt',
        'llama_response',
    ];

    protected $casts = [
        'ingredients' => 'array',
        'food_preparation_materials' => 'array',
        'sprouts_material' => 'array',
        'calories' => 'integer',
        'protein' => 'decimal:2',
        'carbs' => 'decimal:2',
        'fats' => 'decimal:2',
        'serving_size' => 'integer',
        'prep_time' => 'integer',
        'cook_time' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
