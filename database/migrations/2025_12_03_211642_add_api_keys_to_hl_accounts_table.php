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
            $table->text('api_key_live')->nullable()->after('paytr_configured_at');
            $table->text('api_key_test')->nullable()->after('api_key_live');
            $table->text('publishable_key_live')->nullable()->after('api_key_test');
            $table->text('publishable_key_test')->nullable()->after('publishable_key_live');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hl_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'api_key_live',
                'api_key_test',
                'publishable_key_live',
                'publishable_key_test',
            ]);
        });
    }
};
