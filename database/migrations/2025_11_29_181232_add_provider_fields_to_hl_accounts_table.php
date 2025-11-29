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
        Schema::table('hl_accounts', function (Blueprint $table) {
            // White-label provider callback URL from HighLevel
            // Only add this column as PayTR fields already exist
            if (!Schema::hasColumn('hl_accounts', 'provider_callback_url')) {
                $table->string('provider_callback_url')->nullable()->after('whitelabel_provider_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hl_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('hl_accounts', 'provider_callback_url')) {
                $table->dropColumn('provider_callback_url');
            }
        });
    }
};
