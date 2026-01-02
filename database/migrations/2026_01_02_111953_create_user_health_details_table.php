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
        Schema::create('user_health_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Basic Information
            $table->integer('age')->nullable();
            $table->decimal('weight', 5, 2)->nullable()->comment('Weight in kg');
            $table->decimal('height', 5, 2)->nullable()->comment('Height in cm');
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            
            // Fitness Plan
            $table->enum('fitness_plan', ['weight_loss', 'weight_gain', 'muscle_building', 'fat_burning'])->nullable();
            
            // Health Information
            $table->text('disease')->nullable()->comment('Disease/Medical conditions');
            $table->text('lifestyle')->nullable();
            $table->text('allergies')->nullable();
            
            // Workout Information
            $table->enum('workout_type', ['gym', 'indoor', 'calisthenics', 'outdoor', 'gymnastic'])->nullable();
            $table->string('workout_intense_type')->nullable();
            $table->string('workout_time')->nullable()->comment('Workout duration/time');
            
            // Meal Information
            $table->enum('meal_type', ['veg', 'non_veg', 'vegan'])->nullable();
            $table->string('type_of_test')->nullable()->comment('Type of test/diet preference');
            
            // Ingredients (stored as JSON array)
            $table->json('ingredients')->nullable()->comment('Selected ingredients checklist');
            $table->enum('ingredient_category', ['veggies', 'mass'])->nullable();
            
            // Food Preparation
            $table->json('food_preparation_materials')->nullable()->comment('Oil and spices');
            $table->string('bread_type')->nullable();
            $table->string('rice_type')->nullable();
            $table->json('sprouts_material')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_health_details');
    }
};
