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
        // Drop the old foreign key if it exists
        Schema::table('announcement_reads', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });
        // Add the new foreign key to users.id
        Schema::table('announcement_reads', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the users foreign key
        Schema::table('announcement_reads', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });
        // Restore the old foreign key to students.id
        Schema::table('announcement_reads', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });
    }
};
