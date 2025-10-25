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
        Schema::create('payment_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('hl_account_id')->constrained()->onDelete('cascade');
            $table->string('location_id')->index();
            $table->string('merchant_oid')->nullable();
            $table->string('transaction_id')->nullable()->index();
            $table->string('provider')->default('paytr');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->string('user_ip')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'created_at']);
            $table->index(['provider', 'error_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_failures');
    }
};
