<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_type',
        'start_date',
        'end_date',
        'fitness_goal',
        'target_calories',
        'plan_data',
        'llama_prompt',
        'llama_response',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'fitness_goal' => 'array',
        'target_calories' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    public function trackings(): HasMany
    {
        return $this->hasManyThrough(MealTracking::class, Meal::class);
    }
}
