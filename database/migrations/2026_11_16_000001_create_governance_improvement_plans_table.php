<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_improvement_plans', function (Blueprint $table) {
            $table->id();
            $table->string('school_code');
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status', 20)->default('issued');
            $table->string('priority', 20)->default('medium');
            $table->text('summary');
            $table->json('objectives');
            $table->json('actions');
            $table->string('source_fingerprint', 64);
            $table->string('model')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_code', 'cycle_id']);
        });

        Schema::create('coaching_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->nullable()->constrained('governance_improvement_plans')->nullOnDelete();
            $table->string('school_code');
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('mode', 20)->default('onsite');
            $table->string('venue')->nullable();
            $table->string('focus')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_code', 'cycle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_sessions');
        Schema::dropIfExists('governance_improvement_plans');
    }
};
