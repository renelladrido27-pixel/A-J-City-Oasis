<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
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

    /** The SOA's Status dropdown; "pending" means every unpaid bill, overdue included. */
    protected const SOA_STATUSES = ['paid' => 'Paid', 'pending' => 'Pending (unpaid)'];

    public function index(Request $request): View
    {
        [$from, $to] = $this->resolveDateRange($request);

        $report = $request->query('report');
        [$sort, $dir] = $this->resolveSort($request);
        $status = $this->resolveStatus($request);
        $tenantId = $this->resolveTenant($request);
        $result = null;

        if ($report && array_key_exists($report, self::REPORTS)) {
            $result = $this->sorted($this->generate($report, $from, $to, $status, $tenantId), $sort, $dir);
        }

        return view('admin.reports.index', [
            'reports' => self::REPORTS,
            'from' => $from,
            'to' => $to,
            'activeReport' => $report,
            'result' => $result,
            'sort' => $sort,
            'dir' => $dir,
            'status' => $status,
            'soaStatuses' => self::SOA_STATUSES,
            'tenantId' => $tenantId,
            'tenants' => $report === 'payment' ? User::where('role', 'tenant')->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $request->query('report');
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        [$from, $to] = $this->resolveDateRange($request);
        $result = $this->sorted($this->generate($report, $from, $to, $this->resolveStatus($request), $this->resolveTenant($request)), ...$this->resolveSort($request));

        $filename = $report.'-'.$from->format('Y-m-d').'-to-'.$to->format('Y-m-d').'.csv';

        $callback = function () use ($result) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $result['columns']);
            foreach ($result['rows'] as $row) {
                fputcsv($handle, $row);
            }
            if (isset($result['total'])) {
                fputcsv($handle, $result['total']);
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

    /** [column index or null for the report's own order, 'asc'|'desc'] */
    protected function resolveSort(Request $request): array
    {
        $sort = $request->query('sort');

        return [
            is_numeric($sort) ? (int) $sort : null,
            $request->query('dir') === 'desc' ? 'desc' : 'asc',
        ];
    }

    /**
     * Sorts the rows by one column. Cells are display strings, so dates
     * ("Nov 05, 2026") and amounts ("1,500.00") are compared as dates and
     * numbers; blanks ("—") always go last.
     */
    protected function sorted(array $result, ?int $col, string $dir): array
    {
        if ($col === null || ! isset($result['columns'][$col])) {
            if ($dir === 'desc') {
                $result['rows'] = array_reverse($result['rows']);
            }

            return $result;
        }

        usort($result['rows'], function ($a, $b) use ($col, $dir) {
            [$x, $y] = [$this->sortKey($a[$col] ?? null), $this->sortKey($b[$col] ?? null)];

            if ($x === null || $y === null) {
                return ($x === null) <=> ($y === null);
            }

            $cmp = is_float($x) && is_float($y) ? $x <=> $y : strnatcasecmp((string) $x, (string) $y);

            return $dir === 'desc' ? -$cmp : $cmp;
        });

        return $result;
    }

    protected function sortKey(?string $cell): float|string|null
    {
        if ($cell === null || $cell === '' || $cell === '—') {
            return null;
        }
        if (preg_match('/^[A-Z][a-z]{2} \d{2}, \d{4}$/', $cell)) {
            return (float) Carbon::createFromFormat('M d, Y', $cell)->startOfDay()->timestamp;
        }
        $number = str_replace(',', '', $cell);

        return is_numeric($number) ? (float) $number : $cell;
    }

    protected function resolveStatus(Request $request): ?string
    {
        $status = $request->query('status');

        return array_key_exists((string) $status, self::SOA_STATUSES) ? $status : null;
    }

    protected function resolveTenant(Request $request): ?int
    {
        $id = $request->query('tenant');

        return ctype_digit((string) $id) ? (int) $id : null;
    }

    protected function generate(string $report, Carbon $from, Carbon $to, ?string $status = null, ?int $tenantId = null): array
    {
        return match ($report) {
            'tenant' => $this->tenantReport($from, $to),
            'payment' => $this->paymentReport($from, $to, $status, $tenantId),
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

    protected function paymentReport(Carbon $from, Carbon $to, ?string $status = null, ?int $tenantId = null): array
    {
        $payments = Payment::with(['lease.tenant', 'lease.room', 'booking.tenant', 'booking.room'])
            ->whereBetween('due_date', [$from, $to])
            ->when($status === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($status === 'pending', fn ($q) => $q->whereIn('status', Payment::UNPAID))
            // A payment belongs to a tenant through its lease (rent) or its booking (upfront).
            ->when($tenantId, fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('lease', fn ($l) => $l->where('tenant_id', $tenantId))
                ->orWhereHas('booking', fn ($b) => $b->where('user_id', $tenantId))))
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
                $payment->paid_at?->format('M d, Y') ?? '—',
                $payment->status === 'paid' ? '#'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) : '—', // same number as the printed receipt
                ucfirst($payment->status),
            ];
        })->all();

        return [
            'title' => 'Payment / Statement of Account',
            'columns' => ['Tenant', 'Room', 'Type', 'Amount (₱)', 'Paid Date', 'Receipt No.', 'Status'],
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
        // By due date, like the SOA: a bill paid early still counts in the month it is for,
        // so this report's count always matches the SOA's "Paid" filter.
        $payments = Payment::with(['lease.tenant', 'lease.room', 'booking.tenant', 'booking.room'])
            ->where('status', 'paid')
            ->whereBetween('due_date', [$from, $to])
            ->orderBy('due_date')
            ->get();

        $rows = $payments->map(function (Payment $payment) {
            $tenant = $payment->lease?->tenant ?? $payment->booking?->tenant;
            $room = $payment->lease?->room ?? $payment->booking?->room;

            return [
                $payment->due_date->format('M d, Y'),
                $payment->paid_at?->format('M d, Y') ?? '—',
                '#'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT), // same number as the printed receipt
                $tenant?->name ?? '—',
                $room ? 'Room '.$room->room_number : '—',
                ucfirst(str_replace('_', ' ', $payment->type)),
                number_format($payment->amount, 2),
            ];
        })->all();

        return [
            'title' => 'Rental Income',
            'columns' => ['Due Date', 'Date Paid', 'Receipt No.', 'Tenant', 'Room', 'Type', 'Amount (₱)'],
            'rows' => $rows,
            // Kept apart from the rows so sorting never moves it.
            'total' => ['', '', '', '', '', 'Total', number_format($payments->sum('amount'), 2)],
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
