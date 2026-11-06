<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * branch_settings lists what can be configured and holds no value. Each branch's value
     * lives in branch_setting_values, whose entity_id points at branch_settings.entity_type.
     * The unique index includes soft-deleted rows on purpose: a branch has one row per setting.
     */
    public function up(): void
    {
        Schema::create('branch_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type')->default('string');
            $table->string('entity_type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branch_setting_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('branch_setting_id')->constrained();
            $table->text('value')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'branch_setting_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_setting_values');
        Schema::dropIfExists('branch_settings');
    }
};
