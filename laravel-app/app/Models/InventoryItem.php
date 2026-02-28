<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'location_id',
        'product_id',
        'on_hand',
        'reorder_point',
        'target_max',
        'last_cost_cents',
        'status',
    ];

    protected $casts = [
        'on_hand' => 'decimal:3',
        'reorder_point' => 'decimal:3',
        'target_max' => 'decimal:3',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
