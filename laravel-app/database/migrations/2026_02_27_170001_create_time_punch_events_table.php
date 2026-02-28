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
        Schema::create('time_punch_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('timeclock_employee_id')->constrained('timeclock_employees')->cascadeOnDelete();
            $table->foreignId('time_shift_id')->nullable()->constrained('time_shifts')->nullOnDelete();
            $table->string('event_type', 20); // CLOCK_IN | CLOCK_OUT
            $table->timestamp('event_at');
            $table->string('source')->default('web');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'location_id', 'event_at']);
            $table->index(['tenant_id', 'timeclock_employee_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_punch_events');
    }
};
