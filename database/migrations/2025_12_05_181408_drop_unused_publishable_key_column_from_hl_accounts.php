<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drop the unused publishable_key column (varchar).
     * We use publishable_key_live and publishable_key_test (text) instead.
     */
    public function up(): void
    {
        Schema::table('hl_accounts', function (Blueprint $table) {
            $table->dropIndex(['publishable_key']);
            $table->dropColumn('publishable_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hl_accounts', function (Blueprint $table) {
            $table->string('publishable_key', 64)->nullable()->unique()->after('location_id');
            $table->index('publishable_key');
        });
    }
};
