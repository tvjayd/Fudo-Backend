<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserFeedbackResource extends JsonResource
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
            'feedback_date' => $this->feedback_date?->toDateString(),
            'meal_feedbacks' => $this->meal_feedbacks,
            'overall_satisfaction' => $this->overall_satisfaction,
            'liked_meals' => $this->liked_meals,
            'disliked_meals' => $this->disliked_meals,
            'suggestions' => $this->suggestions,
            'hunger_level_met' => $this->hunger_level_met,
            'energy_level' => $this->energy_level,
            'additional_notes' => $this->additional_notes,
            'used_for_next_plan' => $this->used_for_next_plan,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
