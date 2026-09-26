@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
<style>
    @media print {
        .sidebar, .topbar, .no-print { display: none !important; }
        .content-area, main { width: 100% !important; }
    }
</style>

<x-page-header title="Reports" subtitle="Generate business reports for a date range" />

<form method="GET" action="{{ route('admin.reports.index') }}" class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body p-4">
        <div class="row g-3 align-items-end mb-4">
            <div class="col-sm-4">
                <label class="form-label small text-muted mb-1">From</label>
                <input type="date" name="from" class="form-control" value="{{ $from->format('Y-m-d') }}">
            </div>
            <div class="col-sm-4">
                <label class="form-label small text-muted mb-1">To</label>
                <input type="date" name="to" class="form-control" value="{{ $to->format('Y-m-d') }}">
            </div>
            <div class="col-sm-4 text-sm-end">
                <span class="text-muted small">{{ $from->format('M j') }} &ndash; {{ $to->format('M j, Y') }}</span>
            </div>
        </div>

        <div class="d-flex flex-column gap-2">
            @foreach ($reports as $key => $label)
                <div class="d-flex align-items-center justify-content-between border rounded px-3 py-2 {{ $activeReport === $key ? 'border-primary bg-primary-subtle' : '' }}">
                    <span class="fw-medium">{{ $label }}</span>
                    <button type="submit" name="report" value="{{ $key }}" class="btn btn-sm btn-outline-dark">Generate</button>
                </div>
            @endforeach
        </div>
    </div>
</form>

@if ($result)
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2 class="h6 mb-0">{{ $result['title'] }}</h2>
                    <p class="text-muted small mb-0">{{ $from->format('M j, Y') }} &ndash; {{ $to->format('M j, Y') }} &middot; {{ count($result['rows']) }} record{{ count($result['rows']) === 1 ? '' : 's' }}</p>
                </div>
                <div class="d-flex gap-2 no-print">
                    <a href="{{ route('admin.reports.export', ['report' => $activeReport, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</a>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / Save as PDF</button>
                </div>
            </div>

            @if (empty($result['rows']))
                <x-empty-state icon="bi-inbox" message="No records for this date range." />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                @foreach ($result['columns'] as $column)
                                    <th>{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result['rows'] as $row)
                                <tr>
                                    @foreach ($row as $cell)
                                        <td>{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@else
    <p class="text-muted small no-print">export as PDF / spreadsheet — pick a date range above, then click Generate on a report.</p>
@endif
@endsection
