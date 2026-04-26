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
    \DB::statement("ALTER TABLE student_difficulties MODIFY COLUMN difficulty ENUM('Introduction','Easy','Medium','Hard','Expert','PostTest') NOT NULL");
}

public function down(): void
{
    \DB::statement("ALTER TABLE student_difficulties MODIFY COLUMN difficulty ENUM('Introduction','Easy','Medium','Hard','Expert') NOT NULL");
}
};
