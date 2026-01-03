<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'country' => $this->country,
            'profile_picture' => $this->profile_picture,
            'role' => $this->role,
            'status' => $this->status,
            'has_health_details' => $this->healthDetail !== null,
            'health_details' => $this->whenLoaded('healthDetail', function () {
                return new UserHealthDetailResource($this->healthDetail);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'health_detail' => new UserHealthDetailResource($this->healthDetail),
            'meal_plans' => new MealPlanResource($this->mealPlans),
        ];
    }
}
