<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds support for dual token storage (Company and Location tokens)
     * to properly handle HighLevel's token hierarchy.
     */
    public function up(): void
    {
        Schema::table('hl_accounts', function (Blueprint $table) {
            // Rename existing access_token to company_access_token for clarity
            // Keep existing access_token as alias during migration
            if (!Schema::hasColumn('hl_accounts', 'company_access_token')) {
                $table->text('company_access_token')->nullable()->after('access_token');
            }

            // Add location-specific token fields
            if (!Schema::hasColumn('hl_accounts', 'location_access_token')) {
                $table->text('location_access_token')->nullable()->after('company_access_token');
            }

            if (!Schema::hasColumn('hl_accounts', 'location_refresh_token')) {
                $table->text('location_refresh_token')->nullable()->after('location_access_token');
            }

            // Track which token type is currently stored in access_token
            // Values: 'Company' or 'Location'
            if (!Schema::hasColumn('hl_accounts', 'token_type')) {
                $table->string('token_type')->default('Company')->after('location_refresh_token');
            }

            // Add third_party_provider_id if it doesn't exist (for provider registration tracking)
            if (!Schema::hasColumn('hl_accounts', 'third_party_provider_id')) {
                $table->string('third_party_provider_id')->nullable()->after('token_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hl_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('hl_accounts', 'company_access_token')) {
                $table->dropColumn('company_access_token');
            }

            if (Schema::hasColumn('hl_accounts', 'location_access_token')) {
                $table->dropColumn('location_access_token');
            }

            if (Schema::hasColumn('hl_accounts', 'location_refresh_token')) {
                $table->dropColumn('location_refresh_token');
            }

            if (Schema::hasColumn('hl_accounts', 'token_type')) {
                $table->dropColumn('token_type');
            }

            if (Schema::hasColumn('hl_accounts', 'third_party_provider_id')) {
                $table->dropColumn('third_party_provider_id');
            }
        });
    }
};
