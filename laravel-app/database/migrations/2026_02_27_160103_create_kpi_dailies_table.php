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
        Schema::create('kpi_dailies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->date('entry_date');
            $table->integer('bank_balance_cents')->default(0);
            $table->integer('safe_balance_cents')->default(0);
            $table->integer('sales_today_cents')->default(0);
            $table->integer('cogs_today_cents')->default(0);
            $table->integer('labor_today_cents')->default(0);
            $table->integer('avg_daily_overhead_cents')->default(0);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'location_id', 'entry_date'], 'kpi_daily_unique_scope_date');
            $table->index(['tenant_id', 'location_id', 'entry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_dailies');
    }
};
