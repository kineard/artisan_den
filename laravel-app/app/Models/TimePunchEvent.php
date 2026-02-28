<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class TimePunchEvent extends Model
{
    protected $fillable = [
        'tenant_id',
        'location_id',
        'timeclock_employee_id',
        'time_shift_id',
        'event_type',
        'event_at',
        'source',
        'note',
    ];

    protected $casts = [
        'event_at' => 'datetime',
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(TimeShift::class, 'time_shift_id');
    }
}
