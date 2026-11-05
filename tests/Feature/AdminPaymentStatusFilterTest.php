<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin "All Payments": the Status dropdown filters by the same status the
 * badge shows, and pagination uses Bootstrap markup (not unstyled Tailwind).
 */
class AdminPaymentStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_status_filter_returns_only_matching_payments(): void
    {
        $upcoming = Payment::factory()->create(['due_date' => now()->addDays(5)]);
        $inGrace = Payment::factory()->create(['due_date' => now()->subDays(10)]);
        $lateUnflagged = Payment::factory()->create(['due_date' => now()->subDays(31)]);
        $flaggedOverdue = Payment::factory()->create(['due_date' => now()->subDays(45), 'status' => 'overdue']);
        $paid = Payment::factory()->create(['due_date' => now()->subDays(40), 'status' => 'paid']);
        $refunded = Payment::factory()->create(['status' => 'refunded']);

        $expected = [
            'pending' => [$upcoming],
            'grace_period' => [$inGrace],
            'overdue' => [$lateUnflagged, $flaggedOverdue],
            'paid' => [$paid],
            'refunded' => [$refunded],
        ];

        foreach ($expected as $status => $payments) {
            $ids = Payment::withDisplayStatus($status)->pluck('id')->sort()->values()->all();
            $this->assertSame(collect($payments)->pluck('id')->sort()->values()->all(), $ids, "filter: {$status}");
        }
    }

    public function test_the_admin_page_filters_by_status_and_keeps_it_across_pages(): void
    {
        $admin = User::factory()->admin()->create();
        Payment::factory()->count(30)->create(['status' => 'paid']);
        Payment::factory()->create(['due_date' => now()->addDays(5)]);

        $response = $this->actingAs($admin)->get(route('admin.payments.index', ['status' => 'paid']));

        $response->assertOk()
            ->assertSee('<option value="paid" selected>Paid</option>', false)
            ->assertSee('page-item', false) // Bootstrap pagination
            ->assertSee('status=paid&amp;page=2', false)
            ->assertDontSee('Record Payment');

        $this->actingAs($admin)->get(route('admin.payments.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Record Payment');
    }

    public function test_an_unknown_status_shows_everything(): void
    {
        $admin = User::factory()->admin()->create();
        Payment::factory()->create(['status' => 'paid']);
        Payment::factory()->create(['due_date' => now()->addDays(5)]);

        $this->actingAs($admin)->get(route('admin.payments.index', ['status' => 'bogus']))
            ->assertOk()
            ->assertSee('<option value="">All statuses</option>', false)
            ->assertDontSee('<a href="'.route('admin.payments.index').'" class="btn btn-outline-secondary">Clear</a>', false)
            ->assertSee('Record Payment')
            ->assertSee('Receipt');
    }
}
