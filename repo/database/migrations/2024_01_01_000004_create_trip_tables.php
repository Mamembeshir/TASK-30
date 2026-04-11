<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->uuid('lead_doctor_id');
            $table->foreign('lead_doctor_id')->references('id')->on('doctors');
            $table->string('specialty', 200);
            $table->string('destination', 300);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('difficulty_level', 20);
            $table->integer('estimated_duration_days')->default(0);
            $table->text('prerequisites')->nullable();
            $table->integer('total_seats');
            $table->integer('available_seats');
            $table->integer('price_cents')->default(0);
            $table->string('status', 20)->default('DRAFT');
            $table->integer('booking_count')->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->uuid('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('trip_signups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trip_id');
            $table->foreign('trip_id')->references('id')->on('trips')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('status', 20)->default('HOLD');
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->uuid('payment_id')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->unique(['trip_id', 'user_id', 'status']); // partial unique handled in app
        });

        Schema::create('seat_holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trip_id');
            $table->foreign('trip_id')->references('id')->on('trips')->cascadeOnDelete();
            $table->uuid('signup_id')->unique();
            $table->foreign('signup_id')->references('id')->on('trip_signups')->cascadeOnDelete();
            $table->timestamp('held_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->boolean('released')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 20)->nullable();
        });

        Schema::create('trip_waitlist_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trip_id');
            $table->foreign('trip_id')->references('id')->on('trips')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->integer('position');
            $table->string('status', 20)->default('WAITING');
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamps();
            $table->unique(['trip_id', 'user_id']);
        });

        Schema::create('trip_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trip_id');
            $table->foreign('trip_id')->references('id')->on('trips')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->smallInteger('rating');
            $table->text('review_text')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->unique(['trip_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_reviews');
        Schema::dropIfExists('trip_waitlist_entries');
        Schema::dropIfExists('seat_holds');
        Schema::dropIfExists('trip_signups');
        Schema::dropIfExists('trips');
    }
};
