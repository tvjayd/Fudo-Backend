<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('feedback_date');
            $table->json('meal_feedbacks')->nullable(); // Feedback for each meal
            $table->integer('overall_satisfaction')->nullable(); // 1-5 rating
            $table->text('liked_meals')->nullable();
            $table->text('disliked_meals')->nullable();
            $table->text('suggestions')->nullable();
            $table->boolean('hunger_level_met')->nullable();
            $table->integer('energy_level')->nullable(); // 1-5 rating
            $table->text('additional_notes')->nullable();
            $table->boolean('used_for_next_plan')->default(false);
            $table->timestamps();
            
            $table->unique(['user_id', 'feedback_date']);
            $table->index(['user_id', 'feedback_date']);
            $table->index('used_for_next_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_feedbacks');
    }
};
