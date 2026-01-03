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
        Schema::create('user_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('recipe_name');
            $table->text('description')->nullable();
            $table->json('ingredients');
            $table->json('food_preparation_materials')->nullable();
            $table->string('bread_type')->nullable();
            $table->string('rice_type')->nullable();
            $table->json('sprouts_material')->nullable();
            $table->integer('calories')->nullable();
            $table->decimal('protein', 5, 2)->nullable();
            $table->decimal('carbs', 5, 2)->nullable();
            $table->decimal('fats', 5, 2)->nullable();
            $table->text('cooking_instructions');
            $table->text('calorie_instructions')->nullable();
            $table->integer('serving_size')->nullable();
            $table->integer('prep_time')->nullable(); // in minutes
            $table->integer('cook_time')->nullable(); // in minutes
            $table->text('llama_prompt')->nullable();
            $table->text('llama_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_recipes');
    }
};
