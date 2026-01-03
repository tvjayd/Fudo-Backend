<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'feedback_date',
        'meal_feedbacks',
        'overall_satisfaction',
        'liked_meals',
        'disliked_meals',
        'suggestions',
        'hunger_level_met',
        'energy_level',
        'additional_notes',
        'used_for_next_plan',
    ];

    protected $casts = [
        'feedback_date' => 'date',
        'meal_feedbacks' => 'array',
        'overall_satisfaction' => 'integer',
        'hunger_level_met' => 'boolean',
        'energy_level' => 'integer',
        'used_for_next_plan' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
