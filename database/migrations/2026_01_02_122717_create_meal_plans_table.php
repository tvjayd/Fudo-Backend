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
        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('plan_type', ['daily', '2_days', 'weekly'])->default('daily');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->json('fitness_goal')->nullable(); // weight_loss, weight_gain, etc.
            $table->integer('target_calories')->nullable();
            $table->text('plan_data')->nullable(); // JSON or text from Llama
            $table->text('llama_prompt')->nullable(); // Store the prompt sent to Llama
            $table->text('llama_response')->nullable(); // Store Llama's response
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['user_id', 'start_date']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_plans');
    }
};
