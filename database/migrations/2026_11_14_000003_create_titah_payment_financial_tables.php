<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('provider',30);
            $table->string('channel',30);
            $table->string('idempotency_key',64)->unique();
            $table->string('gateway_order_id',80)->unique();
            $table->string('gateway_transaction_id',100)->nullable();
            $table->decimal('amount',12,2);
            $table->char('currency',3)->default('IDR');
            $table->string('status',30)->default('creating');
            $table->text('qr_code_url')->nullable();
            $table->text('payment_url')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_id','attempt_number']);
            $table->index(['payment_id','status']);
        });

        Schema::create('payment_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('reference',80)->unique();
            $table->decimal('gross_amount',12,2);
            $table->decimal('platform_fee',12,2);
            $table->decimal('net_amount',12,2);
            $table->string('source',30);
            $table->string('status',20)->default('settled');
            $table->timestamp('settled_at');
            $table->timestamps();
            $table->index(['business_id','settled_at']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('entry_group_id');
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_code',50);
            $table->decimal('debit',14,2)->default(0);
            $table->decimal('credit',14,2)->default(0);
            $table->string('source_type',40);
            $table->unsignedBigInteger('source_id');
            $table->string('idempotency_key',120)->unique();
            $table->string('description',255);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id','account_code','created_at']);
            $table->index(['source_type','source_id']);
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount',12,2);
            $table->string('bank_name',60);
            $table->string('bank_account_number',60);
            $table->string('bank_account_name',120);
            $table->string('status',20)->default('requested');
            $table->string('idempotency_key',64)->unique();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference',100)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['business_id','status']);
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount',12,2);
            $table->text('reason');
            $table->string('provider_reference',100)->nullable();
            $table->string('status',20)->default('requested');
            $table->string('idempotency_key',64)->unique();
            $table->json('response_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['payment_id','status']);
        });

        Schema::create('payment_reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('provider',30)->default('midtrans');
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status',30)->default('running');
            $table->unsignedInteger('checked_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->json('details')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['provider','started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_runs');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payment_settlements');
        Schema::dropIfExists('payment_attempts');
    }
};
