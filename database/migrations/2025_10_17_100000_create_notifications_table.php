<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            // $table->string('type'); // removed type column
            $table->string('title');
            $table->text('message');
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('is_for_all')->default(false); // true if notification is for all students
            $table->timestamps();

            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index('exam_id');
            $table->index('user_id');
            // $table->index('type'); // removed type index
            $table->index('created_at');
            $table->index('is_for_all');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};
