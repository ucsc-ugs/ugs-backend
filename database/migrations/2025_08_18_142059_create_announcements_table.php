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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->enum('audience', ['all', 'exam-specific', 'department-specific', 'year-specific']);
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('year_level')->nullable();
            $table->date('expiry_date');
            $table->dateTime('publish_date')->nullable();
            $table->enum('status', ['published', 'draft', 'scheduled']);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent']);
            $table->enum('category', ['general', 'exam', 'academic', 'administrative', 'emergency']);
            $table->json('tags')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->boolean('sms_notifications_enabled')->default(false);
            $table->boolean('push_notifications_enabled')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
