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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade')->nullable();
            $table->string('description');
            $table->enum('status', ['pending', 'resolved', 'rejected'])->default('pending');
            $table->string('created_by')->nullable(); // User who created the complaint
            $table->string('updated_by')->nullable(); // User who last updated the complaint
            $table->string('resolved_by')->nullable(); // User who resolved the complaint
            $table->string('rejected_by')->nullable(); // User who rejected the complaint
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
