<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE quizzes MODIFY COLUMN difficulty ENUM(
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
        ) NOT NULL DEFAULT 'Easy'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE quizzes MODIFY COLUMN difficulty ENUM(
            'Introduction',
            'Easy',
            'Medium',
            'Hard'
        ) NOT NULL DEFAULT 'Easy'");
    }
};
