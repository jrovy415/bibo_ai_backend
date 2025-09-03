<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['middle_name', 'email', 'gender', 'birthday']);

            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('middle_name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('gender')->nullable();
            $table->date('birthday')->nullable();

            Schema::table('users', function (Blueprint $table) {
                $table->uuid('role_id')->nullable()->after('id');
            });

            // Populate role_id for existing users
            $defaultRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
            DB::table('users')->update(['role_id' => $defaultRoleId]);
            //
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            });
        });
    }
};
