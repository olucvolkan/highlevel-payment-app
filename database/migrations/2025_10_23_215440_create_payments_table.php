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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hl_account_id')->constrained()->onDelete('cascade');
            $table->string('location_id')->index();
            $table->string('contact_id')->nullable()->index();
            $table->string('merchant_oid')->unique();
            $table->string('transaction_id')->nullable()->index();
            $table->string('charge_id')->nullable();
            $table->string('subscription_id')->nullable()->index();
            $table->string('order_id')->nullable();
            $table->string('provider')->default('paytr')->index();
            $table->string('provider_payment_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('TRY');
            $table->string('status')->default('pending')->index();
            $table->string('payment_mode')->default('payment');
            $table->string('payment_type')->default('card');
            $table->integer('installment_count')->default(0);
            $table->string('user_ip')->nullable();
            $table->string('email')->nullable();
            $table->text('user_basket')->nullable();
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['location_id', 'status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
