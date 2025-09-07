<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // quizzes
        Schema::create('quizzes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('teacher_id'); // FK -> users.id (teacher/admin who created quiz)
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->enum('grade_level', ['Kinder', 'Grade 1']);
            $table->enum('difficulty', ['Easy', 'Medium', 'Hard'])->default('Easy'); // new separator
            $table->integer('time_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
        });

        // questions
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quiz_id');
            $table->text('question_text');
            $table->enum('question_type', ['multiple_choice', 'true_false']);
            $table->integer('points')->default(1);
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
        });

        // choices
        Schema::create('choices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('question_id');
            $table->string('choice_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade');
        });

        // quiz attempts
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quiz_id');
            $table->uuid('student_id'); // FK -> students.id
            $table->integer('score')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });

        // answers
        Schema::create('answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attempt_id');
            $table->uuid('question_id');
            $table->uuid('choice_id')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->foreign('attempt_id')->references('id')->on('quiz_attempts')->onDelete('cascade');
            $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade');
            $table->foreign('choice_id')->references('id')->on('choices')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('choices');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('quizzes');
    }
};
