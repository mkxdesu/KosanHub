<?php

namespace App\Services;

use App\Models\Contract;
use Carbon\Carbon;

class ContractService
{
    /**
     * Hitung nominal invoice untuk kontrak pada periode tertentu.
     * Bulan pertama dihitung proporsional berdasarkan hari aktif di bulan itu.
     */
    public function calculateProportionalAmount(Contract $contract, string $period): float
    {
        $periodDate = Carbon::createFromFormat('Y-m', $period);
        $contractStart = Carbon::parse($contract->start_date);

        if ($contractStart->format('Y-m') !== $periodDate->format('Y-m')) {
            return (float) $contract->monthly_rent;
        }

        $daysInMonth = $periodDate->daysInMonth;
        $daysActive = $contractStart->diffInDays($periodDate->copy()->endOfMonth()) + 1;

        if ($daysActive <= 0 || $daysInMonth <= 0) {
            return 0.0;
        }

        return round((float) $contract->monthly_rent * ($daysActive / $daysInMonth), 2);
    }

    /**
     * Hitung denda keterlambatan.
     * 2% per minggu, maksimal 10% total tagihan.
     */
    public function calculatePenalty(float $amount, Carbon|string $dueDate): float
    {
        $due = $dueDate instanceof Carbon ? $dueDate : Carbon::parse($dueDate);

        if (!$due->isPast()) {
            return 0.0;
        }

        $weeksLate = max(0, $due->diffInWeeks(now()));
        $penaltyRate = min($weeksLate * 0.02, 0.10);

        return round((float) $amount * $penaltyRate, 2);
    }
}