<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class TimeclockEmployee extends Model
{
    protected $fillable = [
        'tenant_id',
        'location_id',
        'full_name',
        'role_name',
        'email',
        'pin_hash',
        'hourly_rate_cents',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(TimeShift::class);
    }

    public function punchEvents(): HasMany
    {
        return $this->hasMany(TimePunchEvent::class);
    }
}
