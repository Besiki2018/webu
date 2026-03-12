<?php

namespace App\Jobs;

use App\Ecommerce\Contracts\EcommerceRsSyncServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessEcommerceRsSync implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public int $syncId
    ) {}

    public function handle(EcommerceRsSyncServiceContract $rsSyncService): void
    {
        $rsSyncService->processSyncById($this->syncId);
    }
}
