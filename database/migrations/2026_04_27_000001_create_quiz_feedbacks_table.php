<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_feedbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('quiz_id');
            $table->uuid('quiz_attempt_id')->nullable();
            $table->enum('feeling', ['easy', 'okay', 'hard']);
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
            $table->foreign('quiz_attempt_id')->references('id')->on('quiz_attempts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_feedbacks');
    }
};
