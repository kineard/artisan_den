@extends('layouts.app')

@section('title', 'Artisan Den - Time Clock')

@section('content')
    <div class="card">
        <div class="row" style="align-items:center;">
            <div style="flex:1 1 auto;">
                <h1 style="margin:0;">Time Clock</h1>
                <p class="muted" style="margin:6px 0 0 0;">Tenant: {{ $tenant->name }} · Location: {{ $location->name }}</p>
            </div>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('timeclock.index') }}">
            <div class="row">
                <div>
                    <label for="location_id">Location</label>
                    <select id="location_id" name="location_id">
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}" @selected($loc->id === $location->id)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:0 0 160px;">
                    <label>&nbsp;</label>
                    <button type="submit">Load</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Add Employee</h2>
        <form method="POST" action="{{ route('timeclock.employees.store') }}">
            @csrf
            <input type="hidden" name="location_id" value="{{ $location->id }}">
            <div class="row">
                <div><label>Full Name</label><input name="full_name" required></div>
                <div><label>Role</label><input name="role_name" value="Employee" required></div>
                <div><label>PIN</label><input name="pin" required></div>
                <div><label>Hourly Rate ($)</label><input name="hourly_rate" value="0.00"></div>
                <div style="flex:0 0 160px;"><label>&nbsp;</label><button type="submit">Create</button></div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Punch In / Out</h2>
        <form method="POST" action="{{ route('timeclock.punch') }}">
            @csrf
            <input type="hidden" name="location_id" value="{{ $location->id }}">
            <div class="row">
                <div>
                    <label>Employee</label>
                    <select name="employee_id" required>
                        <option value="">Select Employee</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }} ({{ $employee->role_name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>PIN</label>
                    <input name="pin" required>
                </div>
                <div>
                    <label>Punch Type</label>
                    <select name="punch_type">
                        <option value="in">Clock In</option>
                        <option value="out">Clock Out</option>
                    </select>
                </div>
                <div>
                    <label>Note</label>
                    <input name="note" placeholder="Optional note">
                </div>
                <div style="flex:0 0 160px;"><label>&nbsp;</label><button type="submit">Submit Punch</button></div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Open Shifts</h2>
        <table>
            <thead>
            <tr>
                <th>Employee</th>
                <th>Clock In</th>
                <th>Source</th>
                <th>Note</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($openShifts as $shift)
                <tr>
                    <td>{{ $shift->employee?->full_name ?? '-' }}</td>
                    <td>{{ optional($shift->clock_in_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $shift->clock_in_source }}</td>
                    <td>{{ $shift->clock_in_note ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No open shifts.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Today Punch Events (latest 20)</h2>
        <table>
            <thead>
            <tr>
                <th>Employee</th>
                <th>Type</th>
                <th>Event Time</th>
                <th>Source</th>
                <th>Note</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($todayPunches as $event)
                <tr>
                    <td>{{ $event->employee?->full_name ?? '-' }}</td>
                    <td>{{ $event->event_type }}</td>
                    <td>{{ optional($event->event_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $event->source }}</td>
                    <td>{{ $event->note ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No punch events yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

