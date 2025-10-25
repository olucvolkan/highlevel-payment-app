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
        Schema::create('hl_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('location_id')->unique()->index();
            $table->string('company_id')->nullable()->index();
            $table->string('user_id')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('integration_id')->nullable();
            $table->string('config_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('scopes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hl_accounts');
    }
};
