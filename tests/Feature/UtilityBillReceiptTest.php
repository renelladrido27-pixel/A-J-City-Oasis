<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\UtilityBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UtilityBillReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function encode(Lease $lease, array $overrides = [])
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.utility-bills.store-any'), array_merge([
                'lease_id' => $lease->id,
                'type' => 'water',
                'amount' => '450.75',
                'due_date' => now()->addDays(12)->toDateString(),
            ], $overrides));
    }

    public function test_admin_can_attach_a_receipt_photo_and_the_tenant_can_see_it(): void
    {
        Storage::fake('public');
        $lease = Lease::factory()->create();

        // create() + mime type rather than image(): XAMPP's PHP has no GD extension.
        $this->encode($lease, ['receipt_photo' => UploadedFile::fake()->create('water-bill.jpg', 300, 'image/jpeg')])
            ->assertRedirect(route('admin.utility-bills.index'));

        $bill = UtilityBill::sole();
        $this->assertStringStartsWith('utility-receipts/', $bill->receipt_photo);
        Storage::disk('public')->assertExists($bill->receipt_photo);

        $this->actingAs($lease->tenant)->get(route('tenant.payments.index'))
            ->assertOk()
            ->assertSee($bill->receiptUrl());
    }

    public function test_the_receipt_photo_is_optional(): void
    {
        $this->encode(Lease::factory()->create())->assertRedirect(route('admin.utility-bills.index'));

        $this->assertNull(UtilityBill::sole()->receipt_photo);
    }

    public function test_only_jpg_png_or_webp_images_are_accepted(): void
    {
        Storage::fake('public');
        $lease = Lease::factory()->create();

        foreach ([
            UploadedFile::fake()->create('bill.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('bill.gif', 100, 'image/gif'),
            UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg'), // over 5 MB
        ] as $file) {
            $this->encode($lease, ['receipt_photo' => $file])->assertSessionHasErrors('receipt_photo');
        }

        $this->assertDatabaseCount('utility_bills', 0);
    }

    public function test_a_tenant_does_not_see_other_tenants_receipts(): void
    {
        Storage::fake('public');
        $lease = Lease::factory()->create();
        $this->encode($lease, ['receipt_photo' => UploadedFile::fake()->create('water-bill.jpg', 300, 'image/jpeg')]);

        $this->actingAs(Lease::factory()->create()->tenant)
            ->get(route('tenant.payments.index'))
            ->assertOk()
            ->assertDontSee(UtilityBill::sole()->receiptUrl());
    }

    public function test_the_encode_forms_use_the_photo_upload_component(): void
    {
        $admin = User::factory()->admin()->create();
        $lease = Lease::factory()->create();

        $this->actingAs($admin)->get(route('admin.utility-bills.create-any'))
            ->assertOk()->assertSee('data-photo-upload', false)->assertSee('enctype="multipart/form-data"', false);
        $this->actingAs($admin)->get(route('admin.utility-bills.create', $lease))
            ->assertOk()->assertSee('data-photo-upload', false)->assertSee('enctype="multipart/form-data"', false);
    }
}
