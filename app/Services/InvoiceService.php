<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        protected ContractService $contractService
    ) {}

    /**
     * Generate tagihan bulanan untuk satu kontrak pada periode tertentu.
     */
    public function generateForContract(Contract $contract, ?string $period = null): Invoice
    {
        $period = $period ?? now()->format('Y-m');

        $exists = Invoice::where('contract_id', $contract->id)
            ->where('period', $period)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'period' => ["Tagihan untuk periode {$period} sudah ada."],
            ]);
        }

        if ($contract->status !== 'active') {
            throw ValidationException::withMessages([
                'contract' => ['Hanya kontrak aktif yang boleh digenerate tagihannya.'],
            ]);
        }

        $amount = $this->contractService->calculateProportionalAmount($contract, $period);
        $dueDate = Carbon::createFromFormat('Y-m', $period)->endOfMonth()->addDays(7);

        return Invoice::create([
            'contract_id' => $contract->id,
            'period' => $period,
            'due_date' => $dueDate,
            'amount' => $amount,
            'penalty' => 0,
            'total' => $amount,
            'status' => 'unpaid',
        ]);
    }

    /**
     * Generate tagihan untuk semua kontrak aktif.
     */
    public function generateMonthly(?string $period = null): array
    {
        $period = $period ?? now()->format('Y-m');
        $created = [];
        $skipped = [];

        $contracts = Contract::where('status', 'active')->get();

        foreach ($contracts as $contract) {
            try {
                $invoice = $this->generateForContract($contract, $period);
                $created[] = $invoice->id;
            } catch (ValidationException $e) {
                $skipped[] = [
                    'contract_id' => $contract->id,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return [
            'period' => $period,
            'created' => $created,
            'skipped' => $skipped,
            'total_created' => count($created),
        ];
    }

    /**
     * Catat pembayaran + update status invoice.
     */
    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data) {
            if ($invoice->isPaid()) {
                throw ValidationException::withMessages([
                    'invoice' => ['Tagihan ini sudah lunas.'],
                ]);
            }

            $amount = (float) ($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Jumlah pembayaran harus lebih dari 0.'],
                ]);
            }

            $penalty = $this->contractService->calculatePenalty(
                (float) $invoice->amount,
                $invoice->due_date
            );
            $totalDue = (float) $invoice->amount + $penalty;

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'method' => $data['method'] ?? 'transfer',
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $invoice->update([
                'penalty' => $penalty,
                'total' => $totalDue,
                'status' => 'paid',
            ]);

            return $payment;
        });
    }

    /**
     * Daftar tagihan yang telah lewat jatuh tempo.
     */
    public function getOverdueInvoices()
    {
        return Invoice::with(['contract.tenant', 'contract.room.property'])
            ->whereIn('status', ['unpaid', 'overdue'])
            ->where('due_date', '<', now())
            ->orderBy('due_date')
            ->get()
            ->map(function ($invoice) {
                /** @var Invoice $invoice */
                $invoice->calculated_penalty = $this->contractService->calculatePenalty(
                    (float) $invoice->amount,
                    $invoice->due_date
                );
                $invoice->total_due = $invoice->amount + $invoice->calculated_penalty;

                return $invoice;
            });
    }
}