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
        Schema::table('meal_trackings', function (Blueprint $table) {
            $table->integer('consumed_calories')->nullable()->after('actual_time')->comment('Actual calories consumed (may differ from meal calories)');
            $table->string('portion_size')->nullable()->after('consumed_calories')->comment('full, half, double, etc.');
            $table->text('modifications')->nullable()->after('portion_size')->comment('What user changed or modified');
            $table->string('consumption_image_url')->nullable()->after('modifications')->comment('Placeholder image URL for consumed meal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_trackings', function (Blueprint $table) {
            $table->dropColumn(['consumed_calories', 'portion_size', 'modifications', 'consumption_image_url']);
        });
    }
};
