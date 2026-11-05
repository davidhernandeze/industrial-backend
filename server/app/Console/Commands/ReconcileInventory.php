<?php

namespace App\Console\Commands;

use App\Services\Inventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileInventory extends Command
{
    protected $signature = 'inventory:reconcile';

    protected $description = 'Check that stored stock matches the sum of the inventory ledger';

    public function handle(Inventory $inventory): int
    {
        $discrepancies = $inventory->discrepancies();

        if ($discrepancies->isEmpty()) {
            $this->components->info('Stock matches the ledger.');

            return self::SUCCESS;
        }

        Log::error('Inventory stock does not match the ledger.', ['rows' => $discrepancies->all()]);

        $this->components->error("{$discrepancies->count()} rows do not match the ledger.");

        return self::FAILURE;
    }
}
