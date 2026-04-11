<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the `idempotency_records` table.
 *
 * This table backed `IdempotencyMiddleware`, which inspected an
 * `X-Idempotency-Key` header on POST/PUT/PATCH requests. The middleware was
 * never attached to any route: in this Livewire-only application, every
 * mutation is dispatched through `POST /livewire/update` (the wire-protocol
 * endpoint), and Livewire does not send that header. The middleware was
 * orphaned dead code.
 *
 * Idempotency is enforced exclusively at the **service layer** — each mutating
 * method (`holdSeat`, `recordPayment`, `purchase`, `renew`, `topUp`,
 * `requestRefund`, etc.) accepts a caller-stable `idempotencyKey` and
 * deduplicates on it against a column on the domain table
 * (`trip_signups.idempotency_key`, `membership_orders.idempotency_key`,
 * `payments.idempotency_key`, `refunds.idempotency_key`). That contract is
 * transport-agnostic and is the sole enforcement path.
 *
 * Removing the middleware, model, and backing table eliminates the ambiguity
 * about "which layer enforces idempotency" that a maintainer would otherwise
 * have to reason about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('idempotency_records');
    }

    public function down(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 64)->index();
            $table->string('endpoint', 200);
            $table->integer('response_status');
            $table->jsonb('response_body')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['idempotency_key', 'endpoint']);
        });
    }
};
