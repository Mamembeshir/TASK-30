<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('tender_type', 20);
            $table->integer('amount_cents');
            $table->string('reference_number', 100)->nullable();
            $table->string('status', 30)->default('RECORDED');
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmation_event_id', 64)->nullable()->unique();
            $table->uuid('settlement_id')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->foreign('payment_id')->references('id')->on('payments');
            $table->integer('amount_cents');
            $table->string('refund_type', 20);
            $table->text('reason');
            $table->string('status', 20)->default('PENDING');
            $table->uuid('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('settlement_date')->unique();
            $table->string('status', 20)->default('OPEN');
            $table->integer('total_payments_cents')->default(0);
            $table->integer('total_refunds_cents')->default(0);
            $table->integer('net_amount_cents')->default(0);
            $table->integer('expected_amount_cents')->default(0);
            $table->integer('variance_cents')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->uuid('reconciled_by')->nullable();
            $table->foreign('reconciled_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->string('statement_file_path', 500)->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        // Add FK from payments to settlements now that settlements table exists
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('settlement_id')->references('id')->on('settlements')->nullOnDelete();
        });

        Schema::create('settlement_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('settlement_id');
            $table->foreign('settlement_id')->references('id')->on('settlements')->cascadeOnDelete();
            $table->string('exception_type', 30);
            $table->text('description');
            $table->integer('amount_cents')->nullable();
            $table->string('status', 20)->default('OPEN');
            $table->uuid('resolved_by')->nullable();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('invoice_number', 50)->unique();
            $table->integer('total_cents');
            $table->string('status', 20)->default('DRAFT');
            $table->timestamp('issued_at')->nullable();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->string('description', 500);
            $table->integer('amount_cents');
            $table->string('line_type', 30);
            $table->uuid('reference_id')->nullable();
            $table->integer('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('settlement_exceptions');
        Schema::table('payments', fn (Blueprint $t) => $t->dropForeign(['settlement_id']));
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
    }
};
