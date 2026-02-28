<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/kpi');
        $response->assertRedirect('/login');
    }

    public function test_employee_cannot_write_kpi(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $response = $this
            ->withSession([
                'auth_user_id' => 99,
                'user_id' => 99,
                'user_name' => 'Employee User',
                'user_role' => 'employee',
            ])
            ->post('/kpi', [
                'entry_date' => '2026-02-27',
                'bank_balance' => '0.00',
                'safe_balance' => '0.00',
                'sales_today' => '0.00',
                'cogs_today' => '0.00',
                'labor_today' => '0.00',
                'avg_daily_overhead' => '0.00',
            ]);

        $response->assertForbidden();
    }
}

