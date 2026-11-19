<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyInvoicesJob;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ContractInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_contract_invoice_and_partial_payment_requests_work(): void
    {
        $property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test Street',
        ]);
        $room = Room::create([
            'property_id' => $property->id,
            'room_number' => 'A-1',
            'monthly_rent' => 1000000,
        ]);
        $tenant = Tenant::create(['name' => 'Test Tenant']);

        $contractResponse = $this->postJson('/api/contracts', [
            'room_id' => $room->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-11-15',
            'monthly_rent' => 1000000,
        ]);

        $contractResponse->assertCreated();
        $contractId = $contractResponse->json('id');

        $invoiceResponse = $this->postJson('/api/invoices/generate', [
            'contract_id' => $contractId,
            'period' => '2026-11',
        ]);

        $invoiceResponse->assertCreated();
        $invoiceId = $invoiceResponse->json('id');

        $paymentResponse = $this->postJson("/api/invoices/{$invoiceId}/pay", [
            'amount' => 500000,
            'method' => 'transfer',
            'reference' => 'TRX-001',
        ]);

        $paymentResponse
            ->assertOk()
            ->assertJsonPath('payment.reference', 'TRX-001');

        $this->assertNotSame('paid', $paymentResponse->json('invoice.status'));
    }

    public function test_monthly_invoice_generation_is_queued_on_redis(): void
    {
        Bus::fake();

        $this->postJson('/api/invoices/generate-monthly', [
            'period' => '2026-11',
        ])
            ->assertAccepted()
            ->assertJsonPath('period', '2026-11');

        Bus::assertDispatched(
            GenerateMonthlyInvoicesJob::class,
            fn (GenerateMonthlyInvoicesJob $job) => $job->period === '2026-11'
                && $job->connection === 'redis'
        );
    }

    public function test_monthly_invoice_generation_rejects_an_invalid_period(): void
    {
        Bus::fake();

        $this->postJson('/api/invoices/generate-monthly', [
            'period' => '2026-13',
        ])->assertUnprocessable();

        Bus::assertNotDispatched(GenerateMonthlyInvoicesJob::class);
    }
}
