<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('careers', function (Blueprint $collection) {
            $collection->id();

            $collection->string('title');
            $collection->string('slug')->unique();
            $collection->string('department')->nullable();
            $collection->string('job_type');
            $collection->string('location')->nullable();
            $collection->string('job_level')->nullable();

            // 🔥 NEW FIELDS
            $collection->integer('vacancies')->default(1);
            $collection->string('salary_range')->nullable();

            $collection->text('job_description');
            $collection->json('job_requirement')->nullable();
            $collection->json('job_responsibility')->nullable();

            $collection->timestamp('closing_date')->nullable();
            $collection->string('status')->default('draft');

            $collection->timestamps();
            $collection->softDeletes(); // 🔥 NEW: Adds the deleted_at column
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
