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
        Schema::create('careers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('department')->nullable();
            $table->string('job_type');
            $table->string('location')->nullable();
            $table->string('job_level')->nullable();
            $table->integer('vacancies')->default(1);
            $table->string('salary_range')->nullable();
            $table->text('job_description');
            $table->json('job_requirement')->nullable();
            $table->json('job_responsibility')->nullable();
            $table->timestamp('closing_date')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('careers');
    }
};
