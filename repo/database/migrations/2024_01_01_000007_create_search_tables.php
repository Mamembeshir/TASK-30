<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 200)->unique();
            $table->string('category', 50)->nullable(); // specialty, destination, title, etc.
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            $table->index('usage_count');
        });

        Schema::create('user_search_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('query', 500);
            $table->jsonb('filters')->nullable();
            $table->integer('result_count')->default(0);
            $table->timestamp('searched_at')->useCurrent();
            $table->index(['user_id', 'searched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_search_histories');
        Schema::dropIfExists('search_terms');
    }
};
