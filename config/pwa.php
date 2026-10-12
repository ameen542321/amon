<?php

return [
    'build_version' => env('PWA_BUILD_VERSION', '2026.10.12.1').'-'.(is_file(public_path('build/manifest.json')) ? substr(hash_file('sha256', public_path('build/manifest.json')), 0, 12) : 'development'),
    'service_worker_enabled' => env('PWA_SERVICE_WORKER_ENABLED', true),
    'updates_enabled' => env('PWA_UPDATES_ENABLED', true),
    'outbox_enabled' => env('PWA_OUTBOX_ENABLED', true),
];
