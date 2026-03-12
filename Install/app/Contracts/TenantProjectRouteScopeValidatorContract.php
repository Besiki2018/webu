<?php

namespace App\Contracts;

use Illuminate\Http\Request;

interface TenantProjectRouteScopeValidatorContract
{
    /**
     * Validate route-model bindings against the current tenant/project/site scope.
     *
     * Returns a structured report with `ok`, `errors`, and `snapshot`.
     *
     * @return array<string, mixed>
     */
    public function validate(Request $request): array;
}

