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
        Schema::create('products', function (Blueprint $collection) {
            $collection->id();
            $collection->string("name");
            $collection->string("description");
            $collection->integer("price");
            $collection->boolean("status")->default(true);
            $collection->string("image")->nullable();
            $collection->index("category_id");
            $collection->objectId("category_id");
            $collection->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
