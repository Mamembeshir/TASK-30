<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original `user_profiles` migration sized the mask columns too small for
 * the masks produced by EncryptionService::applyMask:
 *
 *   - SSN mask:     "***-**-6789"   → 11 chars (column was varchar(10))
 *   - Address mask: "*** *** ******"+ → can exceed varchar(50)
 *
 * This migration widens both columns so the Profile UI can persist real
 * masked values without truncation errors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('ssn_fragment_mask', 30)->nullable()->change();
            $table->string('address_mask',     200)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('ssn_fragment_mask', 10)->nullable()->change();
            $table->string('address_mask',      50)->nullable()->change();
        });
    }
};
