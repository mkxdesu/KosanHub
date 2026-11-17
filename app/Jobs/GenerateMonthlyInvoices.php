<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateMonthlyInvoices implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $activeContracts = Contract::where('status', 'active')->get();

        foreach ($activeContracts as $contract) {
            $period = now()->format('Y-m');

            // Cek apakah sudah ada tagihan untuk periode ini
            if (Invoice::where('contract_id', $contract->id)->where('period', $period)->exists()) {
                continue;
            }

            $amount = $this->calculateProportionalAmount($contract);
            $penalty = $this->calculatePenalty($contract); // jika ada tagihan sebelumnya yang overdue

            Invoice::create([
                'contract_id' => $contract->id,
                'period'      => $period,
                'due_date'    => now()->addDays(7),
                'amount'      => $amount,
                'penalty'     => $penalty,
                'total'       => $amount + $penalty,
                'status'      => 'unpaid',
            ]);
        }
    }
}
