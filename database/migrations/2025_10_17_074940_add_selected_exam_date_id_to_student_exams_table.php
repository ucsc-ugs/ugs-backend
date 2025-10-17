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
        Schema::table('student_exams', function (Blueprint $table) {
            $table->unsignedBigInteger('selected_exam_date_id')->nullable()->after('exam_id');
            $table->foreign('selected_exam_date_id')->references('id')->on('exam_dates')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_exams', function (Blueprint $table) {
            $table->dropForeign(['selected_exam_date_id']);
            $table->dropColumn('selected_exam_date_id');
        });
    }
};
