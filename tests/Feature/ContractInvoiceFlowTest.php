<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyInvoicesJob;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Services\ContractService;
use Carbon\Carbon;
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

    public function test_health_endpoint_reports_database_connection(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'connected');
    }

    public function test_a_room_cannot_have_two_active_contracts(): void
    {
        ['room' => $room, 'tenant' => $tenant] = $this->createContractFixture();

        $payload = [
            'room_id' => $room->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-11-15',
            'monthly_rent' => 1000000,
        ];

        $this->postJson('/api/contracts', $payload)->assertCreated();

        $secondTenant = Tenant::create(['name' => 'Second Test Tenant']);
        $this->postJson('/api/contracts', [
            ...$payload,
            'tenant_id' => $secondTenant->id,
        ])->assertUnprocessable();

        $this->assertSame(1, $room->contracts()->where('status', 'active')->count());
    }

    public function test_first_month_invoice_is_prorated_from_the_contract_start_date(): void
    {
        ['room' => $room, 'tenant' => $tenant] = $this->createContractFixture();
        $contract = $this->postJson('/api/contracts', [
            'room_id' => $room->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-11-16',
            'monthly_rent' => 1000000,
        ])->assertCreated()->json();

        $this->postJson('/api/invoices/generate', [
            'contract_id' => $contract['id'],
            'period' => '2026-11',
        ])
            ->assertCreated()
            ->assertJsonPath('amount', '500000.00');

        $this->postJson('/api/invoices/generate', [
            'contract_id' => $contract['id'],
            'period' => '2026-11',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_late_payment_penalty_is_two_percent_per_week_and_capped_at_ten_percent(): void
    {
        Carbon::setTestNow('2026-11-21 12:00:00');

        try {
            $service = new ContractService;

            $this->assertSame(
                2000.0,
                $service->calculatePenalty(100000, Carbon::parse('2026-11-14'))
            );
            $this->assertSame(
                10000.0,
                $service->calculatePenalty(100000, Carbon::parse('2026-10-10'))
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createContractFixture(): array
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

        return ['room' => $room, 'tenant' => $tenant];
    }
}
