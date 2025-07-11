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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id(); // This will be the foreign key to users.id
            $table->boolean('local');
            $table->string('passport_nic')->unique();
            $table->timestamps();

            // Make the id a foreign key to users.id
            $table->foreign('id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('org_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('students');
        Schema::dropIfExists('super_admins');
        Schema::dropIfExists('org_admins');
    }
};
