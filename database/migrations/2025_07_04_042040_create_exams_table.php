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
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('exam_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->dateTime('date');
            $table->string('location')->nullable();
            $table->timestamps();
        });

        Schema::create('student_exams', function (Blueprint $table) {
            $table->id();
            $table->string('index_number')->unique();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->enum('status', ['registered', 'rejected', 'expired'])->default('registered');
            $table->boolean('attended')->default(false);
            $table->string('result')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_exams');
        Schema::dropIfExists('exam_dates');
        Schema::dropIfExists('exams');
    }
};
