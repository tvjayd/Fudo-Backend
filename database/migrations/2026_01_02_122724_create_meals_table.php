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
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained()->onDelete('cascade');
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snack'])->default('breakfast');
            $table->date('meal_date');
            $table->time('suggested_time')->nullable();
            $table->string('dish_name');
            $table->text('description')->nullable();
            $table->json('ingredients')->nullable(); // Array of ingredients
            $table->json('food_preparation_materials')->nullable(); // Oil, spices, etc.
            $table->string('bread_type')->nullable();
            $table->string('rice_type')->nullable();
            $table->json('sprouts_material')->nullable();
            $table->integer('calories')->nullable();
            $table->decimal('protein', 5, 2)->nullable(); // in grams
            $table->decimal('carbs', 5, 2)->nullable(); // in grams
            $table->decimal('fats', 5, 2)->nullable(); // in grams
            $table->text('cooking_instructions')->nullable();
            $table->text('calorie_instructions')->nullable();
            $table->boolean('is_customizable')->default(true);
            $table->timestamps();
            
            $table->index(['meal_plan_id', 'meal_date']);
            $table->index(['meal_date', 'meal_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
