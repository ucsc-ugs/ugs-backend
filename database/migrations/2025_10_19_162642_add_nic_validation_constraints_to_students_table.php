<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, let's check and fix any existing data that doesn't meet constraints
        $students = DB::table('students')->get();
        
        foreach ($students as $student) {
            $needsUpdate = false;
            $newValue = $student->passport_nic;
            
            if ($student->local) {
                // For local students, should be NIC format
                if (!preg_match('/^[0-9]{9}[VX]$/', $student->passport_nic) && 
                    !preg_match('/^[0-9]{12}$/', $student->passport_nic)) {
                    // Convert to a valid test NIC format
                    $newValue = '92' . str_pad($student->id, 3, '0', STR_PAD_LEFT) . '6789V';
                    $needsUpdate = true;
                }
            } else {
                // For foreign students, should be passport format
                if (!preg_match('/^[A-Z0-9]{6,15}$/', $student->passport_nic)) {
                    // Convert to a valid test passport format
                    $newValue = 'P' . str_pad($student->id, 7, '0', STR_PAD_LEFT);
                    $needsUpdate = true;
                }
            }
            
            if ($needsUpdate) {
                DB::table('students')
                    ->where('id', $student->id)
                    ->update(['passport_nic' => $newValue]);
            }
        }
        
        Schema::table('students', function (Blueprint $table) {
            // Add index for better performance on passport_nic searches
            $table->index('passport_nic', 'idx_students_passport_nic');
            
            // Add length constraints
            $table->string('passport_nic', 20)->change(); // Ensure max length
        });
        
        // Add a database-level check for NIC format if using PostgreSQL
        // Only add constraint after data is clean
        if (config('database.default') === 'pgsql') {
            DB::statement("
                ALTER TABLE students 
                ADD CONSTRAINT chk_students_nic_format 
                CHECK (
                    CASE 
                        WHEN local = true THEN 
                            (passport_nic ~* '^[0-9]{9}[VX]$' OR passport_nic ~* '^[0-9]{12}$')
                        ELSE 
                            (passport_nic ~* '^[A-Z0-9]{6,15}$')
                    END
                )
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Remove the index
            $table->dropIndex('idx_students_passport_nic');
        });
        
        // Remove check constraint if using PostgreSQL
        if (config('database.default') === 'pgsql') {
            DB::statement("ALTER TABLE students DROP CONSTRAINT IF EXISTS chk_students_nic_format");
        }
    }
};
