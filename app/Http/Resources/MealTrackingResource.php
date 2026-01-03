<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealTrackingResource extends JsonResource
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
            'meal_id' => $this->meal_id,
            'tracking_date' => $this->tracking_date?->toDateString(),
            'reminder_time' => $this->reminder_time?->format('H:i'),
            'actual_time' => $this->actual_time?->format('H:i'),
            'status' => $this->status,
            'reminder_sent' => $this->reminder_sent,
            'reminder_sent_at' => $this->reminder_sent_at?->toISOString(),
            'notes' => $this->notes,
            'marked_before_sleep' => $this->marked_before_sleep,
            'marked_at' => $this->marked_at?->toISOString(),
            'consumed_calories' => $this->consumed_calories,
            'portion_size' => $this->portion_size,
            'modifications' => $this->modifications,
            'consumption_image_url' => $this->consumption_image_url,
            'meal' => new MealResource($this->whenLoaded('meal')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
