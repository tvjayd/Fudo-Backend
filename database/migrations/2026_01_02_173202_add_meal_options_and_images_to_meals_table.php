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
        Schema::table('meals', function (Blueprint $table) {
            $table->integer('option_number')->nullable()->after('meal_type')->comment('1, 2, or 3 for meal options');
            $table->boolean('is_selected')->default(false)->after('option_number');
            $table->string('image_url')->nullable()->after('dish_name');
            
            $table->index(['meal_plan_id', 'meal_date', 'meal_type', 'option_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropIndex(['meal_plan_id', 'meal_date', 'meal_type', 'option_number']);
            $table->dropColumn(['option_number', 'is_selected', 'image_url']);
        });
    }
};
