<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('school')->after('email');
            $table->string('status', 20)->default('active')->after('role');
            $table->string('office')->nullable()->after('status');
            $table->string('school_name')->nullable()->after('office');
            $table->string('school_code')->nullable()->after('school_name');
            $table->string('position')->nullable()->after('school_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status', 'office', 'school_name', 'school_code', 'position']);
        });
    }
};
