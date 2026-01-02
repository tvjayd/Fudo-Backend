<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHealthDetail extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'age',
        'weight',
        'height',
        'gender',
        'fitness_plan',
        'disease',
        'lifestyle',
        'allergies',
        'workout_type',
        'workout_intense_type',
        'workout_time',
        'meal_type',
        'type_of_test',
        'ingredients',
        'ingredient_category',
        'food_preparation_materials',
        'bread_type',
        'rice_type',
        'sprouts_material',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'ingredients' => 'array',
        'food_preparation_materials' => 'array',
        'sprouts_material' => 'array',
    ];

    /**
     * Get the user that owns the health detail.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
