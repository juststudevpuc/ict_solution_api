<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->index();
            $table->text('description')->nullable();
            $table->string('image_url');
            $table->string('image_public_id')->nullable();
            $table->json('tech_stack');
            $table->string('icon', 50)->default('globe');
            $table->string('live_url')->nullable();
            $table->boolean('is_published')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
