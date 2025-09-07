<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // question types table
        Schema::create('question_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique(); // e.g., multiple_choice, true_false
            $table->timestamps();
        });

        // add foreign key to questions table
        Schema::table('questions', function (Blueprint $table) {
            $table->uuid('question_type_id')->nullable()->after('quiz_id');

            $table->foreign('question_type_id')
                ->references('id')
                ->on('question_types')
                ->onDelete('restrict');

            $table->dropColumn('question_type');
        });
    }

    public function down(): void
    {
        // only drop the FK and column, keep existing questions
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['question_type_id']);
            $table->dropColumn('question_type_id');
            $table->enum('question_type', ['multiple_choice', 'true_false', 'reading']);
        });

        Schema::dropIfExists('question_types');
    }
};
