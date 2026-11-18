<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportSortTest extends TestCase
{
    use RefreshDatabase;

    private function amounts(array $query): array
    {
        $admin = User::factory()->admin()->create();
        $rows = $this->actingAs($admin)
            ->get(route('admin.reports.index', ['report' => 'rental_income', 'from' => '2026-11-01', 'to' => '2026-11-30'] + $query))
            ->assertOk()
            ->viewData('result');

        return [array_column($rows['rows'], 5), $rows['total'][5]];
    }

    public function test_reports_sort_by_the_chosen_column_as_numbers_and_dates(): void
    {
        $lease = Lease::factory()->create();
        foreach ([['12,000', '2026-11-03'], ['900', '2026-11-20'], ['3,500', '2026-11-10']] as [$amount, $paid]) {
            Payment::factory()->create(['lease_id' => $lease->id, 'amount' => str_replace(',', '', $amount), 'status' => 'paid', 'due_date' => $paid, 'paid_at' => $paid]);
        }

        // Amount column, highest first: compared as numbers, not text ("900" < "12,000").
        [$amounts, $total] = $this->amounts(['sort' => 5, 'dir' => 'desc']);
        $this->assertSame(['12,000.00', '3,500.00', '900.00'], $amounts);
        $this->assertSame('16,400.00', $total); // the total row stays out of the sort

        // Date Paid column, newest first: compared as dates, not text.
        [$amounts] = $this->amounts(['sort' => 1, 'dir' => 'desc']);
        $this->assertSame(['900.00', '3,500.00', '12,000.00'], $amounts);

        // A bogus column falls back to the report's own order (due date, oldest first).
        [$amounts] = $this->amounts(['sort' => 99]);
        $this->assertSame(['12,000.00', '3,500.00', '900.00'], $amounts);
    }

    public function test_rental_income_counts_the_same_paid_bills_as_the_soa(): void
    {
        $lease = Lease::factory()->create();
        Payment::factory()->create(['lease_id' => $lease->id, 'amount' => 3500, 'status' => 'paid', 'due_date' => '2026-11-01', 'paid_at' => '2026-10-30']);
        Payment::factory()->create(['lease_id' => $lease->id, 'amount' => 1000, 'status' => 'paid', 'due_date' => '2026-11-05', 'paid_at' => '2026-11-04']);
        Payment::factory()->create(['lease_id' => $lease->id, 'amount' => 9999, 'status' => 'paid', 'due_date' => '2026-10-28', 'paid_at' => '2026-11-02']);

        [$income, $total] = $this->amounts([]);
        $soa = $this->get(route('admin.reports.index', ['report' => 'payment', 'status' => 'paid', 'from' => '2026-11-01', 'to' => '2026-11-30']))->viewData('result')['rows'];

        $this->assertSame(['3,500.00', '1,000.00'], $income);
        $this->assertSame('4,500.00', $total);
        $this->assertCount(count($income), $soa);
    }
}
