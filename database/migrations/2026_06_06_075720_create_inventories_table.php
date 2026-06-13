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
        Schema::create('inventories', function (Blueprint $collection) {
            $collection->id();

            $collection->index("product_id");
            $collection->objectId("product_id");

            $collection->string("type");
            $collection->integer("qty");
            $collection->integer("stock_left");

            $collection->index("order_id");
            $collection->objectId("order_id")->nullable();

            $collection->string("remark")->nullable();
            $collection->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
