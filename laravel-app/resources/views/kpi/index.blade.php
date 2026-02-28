@extends('layouts.app')

@section('title', 'Artisan Den - KPI')

@section('page_styles')
h1 { margin: 0 0 8px 0; }
th, td { font-size: 14px; }
@endsection

@section('content')
    <div class="card">
        <h1>KPI Daily Entry</h1>
        <p class="muted">Tenant: {{ $tenant->name }} · Location: {{ $location->name }}</p>
        <form method="GET" action="{{ route('kpi.index') }}">
            <div class="grid">
                <div>
                    <label for="location_id">Location</label>
                    <select id="location_id" name="location_id">
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}" @selected($loc->id === $location->id)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date">Date</label>
                    <input id="date" type="date" name="date" value="{{ $entryDate }}">
                </div>
            </div>
            <div style="margin-top: 10px;">
                <button type="submit">Load Date</button>
            </div>
        </form>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('kpi.store') }}">
            @csrf
            <input type="hidden" name="location_id" value="{{ $location->id }}">
            <div class="grid">
                <div>
                    <label for="entry_date">Entry Date</label>
                    <input id="entry_date" type="date" name="entry_date" value="{{ old('entry_date', $entryDate) }}" required>
                </div>
                <div>
                    <label for="bank_balance">Bank Balance ($)</label>
                    <input id="bank_balance" name="bank_balance" value="{{ old('bank_balance', number_format((($kpi->bank_balance_cents ?? 0) / 100), 2, '.', '')) }}" required>
                </div>
                <div>
                    <label for="safe_balance">Safe Balance ($)</label>
                    <input id="safe_balance" name="safe_balance" value="{{ old('safe_balance', number_format((($kpi->safe_balance_cents ?? 0) / 100), 2, '.', '')) }}" required>
                </div>
                <div>
                    <label for="sales_today">Sales Today ($)</label>
                    <input id="sales_today" name="sales_today" value="{{ old('sales_today', number_format((($kpi->sales_today_cents ?? 0) / 100), 2, '.', '')) }}" required>
                </div>
                <div>
                    <label for="cogs_today">COGS Today ($)</label>
                    <input id="cogs_today" name="cogs_today" value="{{ old('cogs_today', number_format((($kpi->cogs_today_cents ?? 0) / 100), 2, '.', '')) }}" required>
                </div>
                <div>
                    <label for="labor_today">Labor Today ($)</label>
                    <input id="labor_today" name="labor_today" value="{{ old('labor_today', number_format((($kpi->labor_today_cents ?? 0) / 100), 2, '.', '')) }}" required>
                </div>
                <div>
                    <label for="avg_daily_overhead">Avg Daily Overhead ($)</label>
                    <input id="avg_daily_overhead" name="avg_daily_overhead" value="{{ old('avg_daily_overhead', number_format((($kpi->avg_daily_overhead_cents ?? 0) / 100), 2, '.', '')) }}" required>
                </div>
            </div>
            <div style="margin-top: 12px;">
                <button type="submit">Save KPI</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top: 0;">Recent Entries (last 14)</h2>
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Sales</th>
                <th>COGS</th>
                <th>Labor</th>
                <th>Overhead</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($recentRows as $row)
                <tr>
                    <td>{{ $row->entry_date }}</td>
                    <td>${{ number_format($row->sales_today_cents / 100, 2) }}</td>
                    <td>${{ number_format($row->cogs_today_cents / 100, 2) }}</td>
                    <td>${{ number_format($row->labor_today_cents / 100, 2) }}</td>
                    <td>${{ number_format($row->avg_daily_overhead_cents / 100, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No entries yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

