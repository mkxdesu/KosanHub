<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'tenant_id',
        'start_date',
        'end_date',
        'monthly_rent',
        'status', // active, ended, cancelled
        'notes',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'monthly_rent' => 'decimal:2',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Hitung jumlah hari sewa di bulan pertama (untuk proporsional).
     */
    public function daysInFirstMonth(): int
    {
        $start = Carbon::parse($this->start_date);
        $endOfMonth = $start->copy()->endOfMonth();

        return $start->diffInDays($endOfMonth) + 1;
    }

    /**
     * Total hari di bulan start_date.
     */
    public function totalDaysInStartMonth(): int
    {
        return Carbon::parse($this->start_date)->daysInMonth;
    }
}