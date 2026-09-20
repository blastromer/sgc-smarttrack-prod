<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movs', function (Blueprint $table) {
            $table->timestamp('removal_requested_at')->nullable()->after('return_reason');
        });
    }

    public function down(): void
    {
        Schema::table('movs', function (Blueprint $table) {
            $table->dropColumn('removal_requested_at');
        });
    }
};
