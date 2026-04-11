<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->integer('price_cents');
            $table->integer('duration_months');
            $table->string('tier', 20);
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('membership_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->uuid('plan_id');
            $table->foreign('plan_id')->references('id')->on('membership_plans');
            $table->string('order_type', 20);
            $table->integer('amount_cents');
            $table->uuid('previous_order_id')->nullable();
            $table->string('status', 30)->default('PENDING');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamp('top_up_eligible_until')->nullable();
            $table->uuid('payment_id')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        // Add self-referential FK after the table (and its PK) is fully created
        Schema::table('membership_orders', function (Blueprint $table) {
            $table->foreign('previous_order_id')->references('id')->on('membership_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_orders');
        Schema::dropIfExists('membership_plans');
    }
};
