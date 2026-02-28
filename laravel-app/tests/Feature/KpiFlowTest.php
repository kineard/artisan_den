<?php

namespace Tests\Feature;

use App\Models\KpiDaily;
use App\Models\Location;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class KpiFlowTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return array<string, mixed>
     */
    private function managerSession(): array
    {
        return [
            'auth_user_id' => 1,
            'user_id' => 1,
            'user_role' => 'manager',
            'user_name' => 'Manager User',
        ];
    }

    public function test_kpi_page_loads_and_bootstraps_default_scope(): void
    {
        $response = $this->withSession($this->managerSession())->get('/kpi');

        $response->assertOk();
        $response->assertSee('KPI Daily Entry');
        $response->assertSee('Tenant:');
        $response->assertSee('Location:');
    }

    public function test_kpi_post_stores_money_as_cents(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withSession($this->managerSession())->get('/kpi')->assertOk();
        $locationId = (int)Location::query()->value('id');

        $this->withSession($this->managerSession())->post('/kpi', [
            'location_id' => $locationId,
            'entry_date' => '2026-02-27',
            'bank_balance' => '1250.45',
            'safe_balance' => '300.00',
            'sales_today' => '987.65',
            'cogs_today' => '432.10',
            'labor_today' => '210.99',
            'avg_daily_overhead' => '75.50',
        ])->assertRedirect('/kpi?location_id=' . $locationId . '&date=2026-02-27');

        $row = KpiDaily::query()->first();
        $this->assertNotNull($row);
        $this->assertSame(125045, $row->bank_balance_cents);
        $this->assertSame(30000, $row->safe_balance_cents);
        $this->assertSame(98765, $row->sales_today_cents);
        $this->assertSame(43210, $row->cogs_today_cents);
        $this->assertSame(21099, $row->labor_today_cents);
        $this->assertSame(7550, $row->avg_daily_overhead_cents);
    }
}

