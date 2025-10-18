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
        Schema::create('exam_date_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_date_id')->constrained('exam_dates')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->integer('priority')->default(1); // 1 = first choice, 2 = second choice, etc.
            $table->integer('current_registrations')->default(0); // Track registrations per hall
            $table->timestamps();

            // Ensure unique combination of exam_date and location
            $table->unique(['exam_date_id', 'location_id']);

            // Index for faster queries
            $table->index(['exam_date_id', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_date_locations');
    }
};
