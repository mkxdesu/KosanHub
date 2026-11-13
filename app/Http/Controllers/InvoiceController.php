<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function index(Request $request)
    {
        $query = Invoice::with(['contract.tenant', 'contract.room.property']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        return response()->json($query->latest()->get());
    }

    public function show(Invoice $invoice)
    {
        return response()->json(
            $invoice->load(['contract.tenant', 'contract.room.property', 'payments'])
        );
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'period'      => 'nullable|date_format:Y-m',
        ]);

        $contract = Contract::findOrFail($data['contract_id']);

        try {
            $invoice = $this->invoiceService->generateForContract(
                $contract,
                $data['period'] ?? null
            );

            return response()->json($invoice->load(['contract.tenant']), 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    public function generateMonthly(Request $request)
    {
        $period = $request->input('period');
        $result = $this->invoiceService->generateMonthly($period);

        return response()->json($result);
    }

    public function pay(Invoice $invoice, Request $request)
    {
        $data = $request->validate([
            'amount'    => 'required|numeric|min:1',
            'paid_at'   => 'nullable|date',
            'method'    => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'note'      => 'nullable|string',
        ]);

        try {
            $payment = $this->invoiceService->recordPayment($invoice, $data);

            return response()->json([
                'message' => 'Pembayaran berhasil dicatat',
                'payment' => $payment,
                'invoice' => $invoice->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    public function overdue()
    {
        return response()->json(
            $this->invoiceService->getOverdueInvoices()
        );
    }
}