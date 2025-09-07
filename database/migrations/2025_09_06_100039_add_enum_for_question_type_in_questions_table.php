<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->enum('question_type', ['multiple_choice', 'true_false', 'reading'])
                ->default('multiple_choice')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->enum('question_type', ['multiple_choice', 'true_false'])
                ->default('multiple_choice')
                ->change();
        });
    }
};
