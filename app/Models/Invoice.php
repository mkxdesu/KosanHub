<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'period',          // YYYY-MM
        'due_date',
        'amount',          // nominal sewa (bisa proporsional)
        'penalty',         // denda
        'total',
        'status',          // unpaid, paid, overdue
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount'   => 'decimal:2',
        'penalty'  => 'decimal:2',
        'total'    => 'decimal:2',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue' || 
               ($this->status === 'unpaid' && $this->due_date->isPast());
    }

    /**
     * Hitung denda keterlambatan: 2% per minggu, maksimal 10%.
     */
    public function calculatePenalty(): float
    {
        if ($this->isPaid() || !$this->due_date->isPast()) {
            return 0;
        }

        $weeksLate = Carbon::parse($this->due_date)->diffInWeeks(now());
        $penaltyRate = min($weeksLate * 0.02, 0.10); // max 10%

        return round($this->amount * $penaltyRate, 2);
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status'  => 'paid',
            'penalty' => $this->calculatePenalty(),
            'total'   => $this->amount + $this->calculatePenalty(),
        ]);
    }
}