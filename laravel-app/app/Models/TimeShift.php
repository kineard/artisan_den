<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class TimeShift extends Model
{
    protected $fillable = [
        'tenant_id',
        'location_id',
        'timeclock_employee_id',
        'clock_in_at',
        'clock_out_at',
        'clock_in_source',
        'clock_out_source',
        'clock_in_note',
        'clock_out_note',
    ];

    protected $casts = [
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(TimeclockEmployee::class, 'timeclock_employee_id');
    }
}
