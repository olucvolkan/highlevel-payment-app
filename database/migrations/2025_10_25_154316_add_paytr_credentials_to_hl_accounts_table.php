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
            $table->string('paytr_merchant_id')->nullable()->after('metadata');
            $table->text('paytr_merchant_key')->nullable()->after('paytr_merchant_id');
            $table->string('paytr_merchant_salt')->nullable()->after('paytr_merchant_key');
            $table->boolean('paytr_test_mode')->default(true)->after('paytr_merchant_salt');
            $table->boolean('paytr_configured')->default(false)->after('paytr_test_mode');
            $table->timestamp('paytr_configured_at')->nullable()->after('paytr_configured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hl_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'paytr_merchant_id',
                'paytr_merchant_key',
                'paytr_merchant_salt',
                'paytr_test_mode',
                'paytr_configured',
                'paytr_configured_at'
            ]);
        });
    }
};
