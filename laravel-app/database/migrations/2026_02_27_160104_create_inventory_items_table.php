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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('on_hand', 12, 3)->default(0);
            $table->decimal('reorder_point', 12, 3)->default(0);
            $table->decimal('target_max', 12, 3)->default(0);
            $table->integer('last_cost_cents')->default(0);
            $table->string('status')->default('ok');
            $table->timestamps();

            $table->unique(['tenant_id', 'location_id', 'product_id'], 'inv_scope_unique_product');
            $table->index(['tenant_id', 'location_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
