<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'city',
        'owner_name',
        'owner_phone',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function activeContractsCount(): int
    {
        return $this->rooms()
            ->whereHas('activeContract')
            ->count();
    }
}