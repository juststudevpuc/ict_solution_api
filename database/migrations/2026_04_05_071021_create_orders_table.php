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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('order_no')->unique();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->text('remark')->nullable();
            $table->string('status')->default('pending');
            $table->integer('duration_days')->default(30);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->string('payment_slip')->nullable();
            $table->string('payway_tran_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
