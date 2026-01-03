<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'meal_id',
        'tracking_date',
        'reminder_time',
        'actual_time',
        'status',
        'reminder_sent',
        'reminder_sent_at',
        'notes',
        'marked_before_sleep',
        'marked_at',
        'consumed_calories',
        'portion_size',
        'modifications',
        'consumption_image_url',
    ];

    protected $casts = [
        'tracking_date' => 'date',
        'reminder_time' => 'datetime',
        'actual_time' => 'datetime',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'marked_before_sleep' => 'boolean',
        'marked_at' => 'datetime',
        'consumed_calories' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }
}
