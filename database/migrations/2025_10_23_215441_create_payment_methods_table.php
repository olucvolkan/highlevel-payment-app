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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hl_account_id')->constrained()->onDelete('cascade');
            $table->string('location_id')->index();
            $table->string('contact_id')->index();
            $table->string('provider')->default('paytr');
            $table->string('utoken')->nullable();
            $table->string('ctoken')->nullable();
            $table->string('card_type')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_brand')->nullable();
            $table->string('expiry_month', 2)->nullable();
            $table->string('expiry_year', 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['location_id', 'contact_id']);
            $table->index(['utoken']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
