<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('level')->default('Public Elementary');
            $table->date('opens_at');
            $table->date('deadline_at');
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->string('result', 20)->nullable();
            $table->timestamp('qa_certified_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'cycle_id']);
        });

        Schema::create('indicator_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('title');
            $table->string('answer', 10)->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'code']);
        });

        Schema::create('movs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('indicator_code', 10)->nullable();
            $table->string('code', 20);
            $table->string('title');
            $table->string('kind', 20)->default('minimum');
            $table->string('original_name')->nullable();
            $table->string('path')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status', 20)->default('draft');
            $table->string('return_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movs');
        Schema::dropIfExists('indicator_answers');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('cycles');
    }
};
