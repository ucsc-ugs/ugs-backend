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
    {        {
        Schema::create('revenue_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_exam_id')->constrained('student_exams')->onDelete('cascade');
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->decimal('revenue', 10, 2);
            $table->decimal('commission', 10, 2);
            $table->decimal('net_revenue', 10, 2);
            $table->string('transaction_reference')->unique();
            $table->enum('status', ['pending', 'completed', 'refunded'])->default('completed');
            $table->timestamp('transaction_date');
            $table->timestamps();
        });
    }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_transactions');
    }
};
