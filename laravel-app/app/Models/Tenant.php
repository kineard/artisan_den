<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function timeclockEmployees(): HasMany
    {
        return $this->hasMany(TimeclockEmployee::class);
    }
}
