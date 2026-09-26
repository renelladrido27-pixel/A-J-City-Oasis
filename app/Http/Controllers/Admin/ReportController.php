<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    protected const REPORTS = [
        'tenant' => 'Tenant Report',
        'payment' => 'Payment / SOA',
        'occupancy' => 'Occupancy',
        'rental_income' => 'Rental Income',
        'maintenance' => 'Maintenance',
    ];

    public function index(Request $request): View
    {
        [$from, $to] = $this->resolveDateRange($request);

        $report = $request->query('report');
        $result = null;

        if ($report && array_key_exists($report, self::REPORTS)) {
            $result = $this->generate($report, $from, $to);
        }

        return view('admin.reports.index', [
            'reports' => self::REPORTS,
            'from' => $from,
            'to' => $to,
            'activeReport' => $report,
            'result' => $result,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $request->query('report');
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        [$from, $to] = $this->resolveDateRange($request);
        $result = $this->generate($report, $from, $to);

        $filename = $report.'-'.$from->format('Y-m-d').'-to-'.$to->format('Y-m-d').'.csv';

        $callback = function () use ($result) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $result['columns']);
            foreach ($result['rows'] as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function resolveDateRange(Request $request): array
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfMonth();

        return [$from, $to];
    }

    protected function generate(string $report, Carbon $from, Carbon $to): array
    {
        return match ($report) {
            'tenant' => $this->tenantReport($from, $to),
            'payment' => $this->paymentReport($from, $to),
            'occupancy' => $this->occupancyReport(),
            'rental_income' => $this->rentalIncomeReport($from, $to),
            'maintenance' => $this->maintenanceReport($from, $to),
        };
    }

    protected function tenantReport(Carbon $from, Carbon $to): array
    {
        $leases = Lease::with(['tenant', 'room.property'])
            ->where('start_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from))
            ->orderBy('start_date')
            ->get();

        $rows = $leases->map(fn (Lease $lease) => [
            $lease->tenant->name,
            $lease->tenant->email,
            $lease->tenant->phone ?? '—',
            $lease->room->property->name,
            'Room '.$lease->room->room_number,
            $lease->start_date->format('M d, Y'),
            $lease->end_date?->format('M d, Y') ?? '—',
            ucfirst($lease->status),
        ])->all();

        return [
            'title' => 'Tenant Report',
            'columns' => ['Tenant', 'Email', 'Phone', 'Property', 'Room', 'Lease Start', 'Lease End', 'Lease Status'],
            'rows' => $rows,
        ];
    }

    protected function paymentReport(Carbon $from, Carbon $to): array
    {
        $payments = Payment::with(['lease.tenant', 'lease.room', 'booking.tenant', 'booking.room'])
            ->whereBetween('due_date', [$from, $to])
            ->orderBy('due_date')
            ->get();

        $rows = $payments->map(function (Payment $payment) {
            $tenant = $payment->lease?->tenant ?? $payment->booking?->tenant;
            $room = $payment->lease?->room ?? $payment->booking?->room;

            return [
                $tenant?->name ?? '—',
                $room ? 'Room '.$room->room_number : '—',
                ucfirst(str_replace('_', ' ', $payment->type)),
                number_format($payment->amount, 2),
                $payment->due_date->format('M d, Y'),
                $payment->paid_at?->format('M d, Y') ?? '—',
                ucfirst($payment->status),
            ];
        })->all();

        return [
            'title' => 'Payment / Statement of Account',
            'columns' => ['Tenant', 'Room', 'Type', 'Amount (₱)', 'Due Date', 'Paid Date', 'Status'],
            'rows' => $rows,
        ];
    }

    protected function occupancyReport(): array
    {
        $rooms = Room::with(['property', 'leases' => fn ($q) => $q->where('status', 'active')->with('tenant')])
            ->orderBy('property_id')->orderBy('floor')->orderBy('room_number')
            ->get();

        $rows = $rooms->map(function (Room $room) {
            $lease = $room->leases->first();

            return [
                $room->property->name,
                'Room '.$room->room_number,
                ucfirst($room->type),
                ucfirst($room->status),
                $lease?->tenant->name ?? '—',
            ];
        })->all();

        return [
            'title' => 'Occupancy (as of today)',
            'columns' => ['Property', 'Room', 'Type', 'Status', 'Current Tenant'],
            'rows' => $rows,
        ];
    }

    protected function rentalIncomeReport(Carbon $from, Carbon $to): array
    {
        $payments = Payment::with(['lease.tenant', 'lease.room', 'booking.tenant', 'booking.room'])
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->orderBy('paid_at')
            ->get();

        $rows = $payments->map(function (Payment $payment) {
            $tenant = $payment->lease?->tenant ?? $payment->booking?->tenant;
            $room = $payment->lease?->room ?? $payment->booking?->room;

            return [
                $payment->paid_at->format('M d, Y'),
                $tenant?->name ?? '—',
                $room ? 'Room '.$room->room_number : '—',
                ucfirst(str_replace('_', ' ', $payment->type)),
                number_format($payment->amount, 2),
            ];
        })->all();

        $rows[] = ['', '', '', 'Total', number_format($payments->sum('amount'), 2)];

        return [
            'title' => 'Rental Income',
            'columns' => ['Date Paid', 'Tenant', 'Room', 'Type', 'Amount (₱)'],
            'rows' => $rows,
        ];
    }

    protected function maintenanceReport(Carbon $from, Carbon $to): array
    {
        $requests = MaintenanceRequest::with(['tenant', 'room'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        $rows = $requests->map(fn (MaintenanceRequest $request) => [
            $request->created_at->format('M d, Y'),
            $request->tenant->name,
            'Room '.$request->room->room_number,
            $request->category,
            ucfirst(str_replace('_', ' ', $request->status)),
            $request->resolved_at?->format('M d, Y') ?? '—',
        ])->all();

        return [
            'title' => 'Maintenance Report',
            'columns' => ['Date Filed', 'Tenant', 'Room', 'Category', 'Status', 'Resolved'],
            'rows' => $rows,
        ];
    }
}
