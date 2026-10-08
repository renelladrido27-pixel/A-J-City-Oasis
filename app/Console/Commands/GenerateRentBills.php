<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Services\RentBillingService;
use Illuminate\Console\Command;

class GenerateRentBills extends Command
{
    protected $signature = 'app:generate-rent-bills';

    protected $description = "Make sure every active lease has next month's rent bill, so tenants always see their next payment";

    public function handle(RentBillingService $billing): int
    {
        $created = 0;

        Lease::where('status', 'active')->with(['room', 'moveOut'])->each(function (Lease $lease) use ($billing, &$created) {
            if ($billing->shouldBillNextMonth($lease) && $billing->billNextMonth($lease)) {
                $created++;
            }
        });

        $this->info("Created {$created} rent bill(s) due ".$billing->nextDueDate()->format('F j, Y').'.');

        return self::SUCCESS;
    }
}
