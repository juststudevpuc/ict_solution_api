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
        Schema::table('users', function (Blueprint $collection) {
            $collection->string('company_name')->nullable();
            $collection->string('company_industry')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $collection) {
            // This allows you to safely rollback the migration if needed
            $collection->dropColumn(['company_name', 'company_industry']);
        });
    }
};
