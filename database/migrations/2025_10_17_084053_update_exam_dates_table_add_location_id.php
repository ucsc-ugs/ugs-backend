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
        Schema::table('exam_dates', function (Blueprint $table) {
            // Add location_id column
            $table->unsignedBigInteger('location_id')->nullable()->after('exam_id');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');

            // Keep the old location column for backward compatibility temporarily
            // We can drop it later after data migration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_dates', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
