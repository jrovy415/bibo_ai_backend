<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds EasyPostTest, MediumPostTest, HardPostTest, ExpertPostTest
     * to the difficulty ENUM in student_difficulties table.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE student_difficulties MODIFY COLUMN difficulty ENUM(
            'Introduction',
            'Easy',
            'EasyPostTest',
            'Medium',
            'MediumPostTest',
            'Hard',
            'HardPostTest',
            'Expert',
            'ExpertPostTest',
            'PostTest'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE student_difficulties MODIFY COLUMN difficulty ENUM(
            'Introduction',
            'Easy',
            'Medium',
            'Hard',
            'Expert',
            'PostTest'
        ) NOT NULL");
    }
};
