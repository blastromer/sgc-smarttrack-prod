<?php

use App\Models\Assessment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assessments', 'school_code')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->string('school_code')->nullable()->after('cycle_id');
            });
        }

        Assessment::query()->with('user')->each(function (Assessment $assessment) {
            $code = $assessment->user?->school_code ?: 'user-'.$assessment->user_id;
            $assessment->update(['school_code' => $code]);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'cycle_id']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['school_code', 'cycle_id']);
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['school_code', 'cycle_id']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'cycle_id']);
            $table->dropColumn('school_code');
        });
    }
};
