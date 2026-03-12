<?php

/**
 * Builder and preview options.
 *
 * @see docs/architecture/WEBU_VISUAL_BUILDER_ARCHITECTURE.md § 11 Performance
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Preview payload cache TTL (seconds)
    |--------------------------------------------------------------------------
    | When the visual builder loads the preview iframe (template-demos with site=),
    | the backend caches the resolved demo payload and theme token layers for this
    | many seconds to avoid repeated buildPayload/resolveForSite on rapid requests.
    | Set to 0 to disable caching.
    */
    'preview_payload_cache_ttl' => (int) env('BUILDER_PREVIEW_PAYLOAD_CACHE_TTL', 15),
];
