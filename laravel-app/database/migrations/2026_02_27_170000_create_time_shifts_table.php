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
        Schema::create('time_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('timeclock_employee_id')->constrained('timeclock_employees')->cascadeOnDelete();
            $table->timestamp('clock_in_at');
            $table->timestamp('clock_out_at')->nullable();
            $table->string('clock_in_source')->default('web');
            $table->string('clock_out_source')->nullable();
            $table->text('clock_in_note')->nullable();
            $table->text('clock_out_note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'location_id', 'clock_out_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_shifts');
    }
};
