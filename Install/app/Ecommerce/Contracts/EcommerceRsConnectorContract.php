<?php

namespace App\Ecommerce\Contracts;

use App\Models\EcommerceRsExport;
use App\Models\EcommerceRsSync;

interface EcommerceRsConnectorContract
{
    /**
     * @return array<string, mixed>
     */
    public function submitExport(EcommerceRsSync $sync, EcommerceRsExport $export): array;
}
