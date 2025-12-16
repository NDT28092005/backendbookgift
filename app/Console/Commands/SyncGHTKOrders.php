<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GhtkOrder;
use App\Services\GHTKService;

class SyncGHTKOrders extends Command
{
    protected $signature = 'ghtk:sync';
    protected $description = 'Sync GHTK order statuses';

    public function handle(GHTKService $ghtk)
    {
        $orders = GhtkOrder::whereNotIn('status', [
            'delivered','cancelled','returned'
        ])->get();

        foreach ($orders as $item) {
            $newStatus = $ghtk->syncOrderStatus($item);
            $this->info("[{$item->label_id}] → {$newStatus}");
        }
    }
}

