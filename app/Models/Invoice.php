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
        'period',
        'due_date',
        'amount',
        'penalty',
        'total',
        'status',
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
        if ($this->status === 'overdue') {
            return true;
        }

        if ($this->status === 'unpaid' && $this->due_date instanceof Carbon) {
            return $this->due_date->isPast();
        }

        return false;
    }

    /**
     * Hitung denda keterlambatan: 2% per minggu, maksimal 10%.
     */
    public function calculatePenalty(): float
    {
        if ($this->isPaid()) {
            return 0;
        }

        $dueDate = $this->due_date instanceof Carbon
            ? $this->due_date
            : Carbon::parse($this->due_date);

        if (!$dueDate->isPast()) {
            return 0;
        }

        $weeksLate   = $dueDate->diffInWeeks(now());
        $penaltyRate = min($weeksLate * 0.02, 0.10);

        return round((float) $this->amount * $penaltyRate, 2);
    }

    public function markAsPaid(): void
    {
        $penalty = $this->calculatePenalty();

        $this->update([
            'status'  => 'paid',
            'penalty' => $penalty,
            'total'   => $this->amount + $penalty,
        ]);
    }
}