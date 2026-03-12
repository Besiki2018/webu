<?php

namespace App\Ecommerce\Services;

use App\Ecommerce\Contracts\EcommerceRsConnectorContract;
use App\Models\EcommerceRsExport;
use App\Models\EcommerceRsSync;

class EcommerceRsSkeletonConnector implements EcommerceRsConnectorContract
{
    public function submitExport(EcommerceRsSync $sync, EcommerceRsExport $export): array
    {
        $seed = $sync->idempotency_key.'|'.$export->export_hash.'|'.$sync->connector;

        return [
            'status' => 'accepted',
            'connector' => $sync->connector,
            'remote_reference' => sprintf('RS-%s', strtoupper(substr(sha1($seed), 0, 16))),
            'accepted_at' => now()->toISOString(),
            'idempotency_key' => $sync->idempotency_key,
            'schema_version' => $export->schema_version,
        ];
    }
}
