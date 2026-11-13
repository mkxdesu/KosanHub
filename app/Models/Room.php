<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'room_number',
        'monthly_rent',
        'status', // available, occupied, maintenance
        'floor',
        'description',
    ];

    protected $casts = [
        'monthly_rent' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Kontrak aktif saat ini (satu kamar hanya boleh punya 1).
     */
    public function activeContract(): HasOne
    {
        return $this->hasOne(Contract::class)->where('status', 'active');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available' && !$this->activeContract()->exists();
    }

    public function markAsOccupied(): void
    {
        $this->update(['status' => 'occupied']);
    }

    public function markAsAvailable(): void
    {
        $this->update(['status' => 'available']);
    }
}