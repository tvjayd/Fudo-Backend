<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meal extends Model
{
    use HasFactory;

    protected $fillable = [
        'meal_plan_id',
        'meal_type',
        'option_number',
        'is_selected',
        'meal_date',
        'suggested_time',
        'dish_name',
        'image_url',
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
        'is_customizable',
    ];

    protected $casts = [
        'meal_date' => 'date',
        'suggested_time' => 'datetime',
        'option_number' => 'integer',
        'is_selected' => 'boolean',
        'ingredients' => 'array',
        'food_preparation_materials' => 'array',
        'sprouts_material' => 'array',
        'calories' => 'integer',
        'protein' => 'decimal:2',
        'carbs' => 'decimal:2',
        'fats' => 'decimal:2',
        'is_customizable' => 'boolean',
    ];

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function trackings(): HasMany
    {
        return $this->hasMany(MealTracking::class);
    }
}
