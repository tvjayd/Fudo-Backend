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
        Schema::create('meal_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('meal_id')->constrained()->onDelete('cascade');
            $table->date('tracking_date');
            $table->time('reminder_time')->nullable();
            $table->time('actual_time')->nullable(); // When user actually ate
            $table->enum('status', ['pending', 'ate', 'not_ate', 'to_be_had', 'skipped'])->default('pending');
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('marked_before_sleep')->default(false);
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'meal_id', 'tracking_date']);
            $table->index(['user_id', 'tracking_date']);
            $table->index(['user_id', 'status']);
            $table->index('reminder_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_trackings');
    }
};
