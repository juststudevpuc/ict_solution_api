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
        Schema::create('job_applications', function (Blueprint $collection) {
            $collection->id();

            $collection->string('career_id')->index();

            $collection->string('first_name');
            $collection->string('last_name');
            $collection->string('email');
            $collection->string('phone');

            // 🔥 NEW FIELDS
            $collection->integer('experience_years')->nullable();
            $collection->string('expected_salary')->nullable();

            $collection->string('cv_url');
            $collection->string('cv_public_id')->nullable();
            $collection->text('cover_letter')->nullable();
            $collection->string('portfolio_url')->nullable();

            $collection->string('status')->default('pending');
            $collection->text('admin_notes')->nullable();

            $collection->timestamps();
            $collection->softDeletes(); // 🔥 NEW: Adds the deleted_at column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
