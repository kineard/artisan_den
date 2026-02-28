<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\TimePunchEvent;
use App\Models\TimeShift;
use App\Models\TimeclockEmployee;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TimeclockFlowTest extends TestCase
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

    public function test_timeclock_page_loads(): void
    {
        $response = $this->withSession($this->managerSession())->get('/timeclock');

        $response->assertOk();
        $response->assertSee('Time Clock', false);
        $response->assertSee('Tenant:');
        $response->assertSee('Location:');
    }

    public function test_can_create_employee_and_punch_in_and_out(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withSession($this->managerSession())->get('/timeclock')->assertOk();
        $locationId = (int) Location::query()->value('id');

        $this->withSession($this->managerSession())->post('/timeclock/employees', [
            'location_id' => $locationId,
            'full_name' => 'Alex Tester',
            'role_name' => 'Employee',
            'pin' => '1234',
            'hourly_rate' => '18.50',
        ])->assertRedirect('/timeclock?location_id=' . $locationId);

        $employee = TimeclockEmployee::query()->where('full_name', 'Alex Tester')->first();
        $this->assertNotNull($employee);

        $this->withSession([
            'auth_user_id' => 1,
            'user_id' => 1,
            'user_role' => 'employee',
            'user_name' => 'Employee User',
        ])->post('/timeclock/punch', [
            'location_id' => $locationId,
            'employee_id' => $employee->id,
            'pin' => '1234',
            'punch_type' => 'in',
            'note' => 'Starting shift',
        ])->assertRedirect('/timeclock?location_id=' . $locationId);

        $openShift = TimeShift::query()->where('timeclock_employee_id', $employee->id)->first();
        $this->assertNotNull($openShift);
        $this->assertNull($openShift->clock_out_at);

        $this->withSession([
            'auth_user_id' => 1,
            'user_id' => 1,
            'user_role' => 'employee',
            'user_name' => 'Employee User',
        ])->post('/timeclock/punch', [
            'location_id' => $locationId,
            'employee_id' => $employee->id,
            'pin' => '1234',
            'punch_type' => 'out',
            'note' => 'Ending shift',
        ])->assertRedirect('/timeclock?location_id=' . $locationId);

        $closedShift = TimeShift::query()->where('id', $openShift->id)->first();
        $this->assertNotNull($closedShift?->clock_out_at);

        $events = TimePunchEvent::query()
            ->where('timeclock_employee_id', $employee->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $events);
        $this->assertSame('CLOCK_IN', $events[0]->event_type);
        $this->assertSame('CLOCK_OUT', $events[1]->event_type);
    }
}

