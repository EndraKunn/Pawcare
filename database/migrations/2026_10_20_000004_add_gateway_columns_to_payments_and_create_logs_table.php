<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'payment_method')) {
                $table->string('payment_method', 30)->default('manual_transfer')->after('amount');
            }
            if (! Schema::hasColumn('payments', 'gateway_provider')) {
                $table->string('gateway_provider', 30)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('payments', 'gateway_reference')) {
                $table->string('gateway_reference', 80)->nullable()->unique()->after('gateway_provider');
            }
            if (! Schema::hasColumn('payments', 'gateway_transaction_id')) {
                $table->string('gateway_transaction_id', 100)->nullable()->after('gateway_reference');
            }
            if (! Schema::hasColumn('payments', 'gateway_payment_type')) {
                $table->string('gateway_payment_type', 50)->nullable()->after('gateway_transaction_id');
            }
            if (! Schema::hasColumn('payments', 'gateway_status')) {
                $table->string('gateway_status', 30)->nullable()->after('gateway_payment_type');
            }
            if (! Schema::hasColumn('payments', 'gateway_payment_url')) {
                $table->text('gateway_payment_url')->nullable()->after('gateway_status');
            }
            if (! Schema::hasColumn('payments', 'gateway_expires_at')) {
                $table->timestamp('gateway_expires_at')->nullable()->after('gateway_payment_url');
            }
        });

        if (! Schema::hasTable('payment_gateway_logs')) {
            Schema::create('payment_gateway_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->string('idempotency_key', 120)->unique();
                $table->string('provider', 30);
                $table->string('order_id', 80);
                $table->string('transaction_status', 40);
                $table->string('status_code', 20)->nullable();
                $table->decimal('gross_amount', 12, 2);
                $table->text('payload')->nullable();
                $table->timestamp('processed_at')->useCurrent();

                $table->index(['payment_id', 'transaction_status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_logs');

        if (Schema::hasColumn('payments', 'payment_method')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn([
                    'payment_method',
                    'gateway_provider',
                    'gateway_reference',
                    'gateway_transaction_id',
                    'gateway_payment_type',
                    'gateway_status',
                    'gateway_payment_url',
                    'gateway_expires_at',
                ]);
            });
        }
    }
};
