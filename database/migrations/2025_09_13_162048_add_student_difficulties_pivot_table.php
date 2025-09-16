<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_difficulties', function (Blueprint $table) {
            $table->uuid('student_id')->primary(); // only 1 row per student
            $table->enum('difficulty', ['Introduction', 'Easy', 'Medium', 'Hard']);
            $table->timestamps();

            $table->foreign('student_id')
                ->references('id')
                ->on('students')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_difficulties');
    }
};
