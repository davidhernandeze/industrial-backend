<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Only App\Services\Inventory writes this column.
     */
    public function up(): void
    {
        Schema::table('branch_product', function (Blueprint $table) {
            $table->decimal('stock', 14, 3)->default(0)->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_product', function (Blueprint $table) {
            $table->dropColumn('stock');
        });
    }
};
