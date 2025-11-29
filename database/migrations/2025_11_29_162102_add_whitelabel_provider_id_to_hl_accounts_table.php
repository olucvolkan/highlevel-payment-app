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
            $table->string('whitelabel_provider_id')->nullable()->after('config_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hl_accounts', function (Blueprint $table) {
            $table->dropColumn('whitelabel_provider_id');
        });
    }
};
