<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_form_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('school_code')->unique();
            $table->string('region')->nullable();
            $table->string('division')->nullable();
            $table->string('school_name')->nullable();
            $table->string('school_address', 500)->nullable();
            $table->string('school_year')->nullable();
            $table->string('contact')->nullable();
            $table->string('email')->nullable();
            $table->string('co_chair_elected')->nullable();
            $table->string('co_chair_designated')->nullable();
            $table->string('secretary_name')->nullable();
            $table->string('school_head_name')->nullable();
            $table->string('venue')->nullable();
            $table->string('meeting_subject')->nullable();
            $table->string('meeting_datetime')->nullable();
            $table->text('meeting_purpose')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_form_profiles');
    }
};
