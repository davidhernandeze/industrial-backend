<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An append-only ledger: rows are never updated or deleted, so it has no deleted_at or
     * updated_at, and corrections are new movements.
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('branch_product_id')->constrained('branch_product');
            $table->string('type');
            $table->decimal('quantity', 12, 3);
            $table->decimal('balance_after', 14, 3);
            $table->nullableMorphs('reference');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamp('created_at');

            $table->index(['branch_product_id', 'id']);
            $table->index(['branch_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
