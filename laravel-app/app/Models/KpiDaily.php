<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class KpiDaily extends Model
{
    protected $fillable = [
        'tenant_id',
        'location_id',
        'entry_date',
        'bank_balance_cents',
        'safe_balance_cents',
        'sales_today_cents',
        'cogs_today_cents',
        'labor_today_cents',
        'avg_daily_overhead_cents',
        'updated_by_user_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
