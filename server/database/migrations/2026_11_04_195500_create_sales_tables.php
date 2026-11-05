<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Payments have their own table so a sale can be split across methods later. Enums are
     * stored as strings and meta is free-form, so new types need no schema change.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained();
            $table->unsignedBigInteger('folio');
            $table->foreignId('user_id')->constrained();
            $table->string('status');
            $table->string('type');
            $table->decimal('total', 12, 2);
            $table->json('meta')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'folio']);
            $table->index(['branch_id', 'created_at']);
            $table->index(['branch_id', 'cancelled_at']);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained();
            $table->string('method');
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_received', 12, 2)->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable()->after('balance_after');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });

        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sales');
    }
};
