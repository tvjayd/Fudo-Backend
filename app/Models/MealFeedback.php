<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MealFeedback extends Model
{
    use HasFactory;

    protected $table = 'meal_feedback';

    protected $fillable = [
        'meal_id',
        'feedback',
        'rating',
    ];

    public function meal()
    {
        return $this->belongsTo(Meal::class);
    }
}
