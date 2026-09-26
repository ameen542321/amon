<?php
return [
    'build_version' => env('PWA_BUILD_VERSION', '2026.09.25.3'),
    'service_worker_enabled' => env('PWA_SERVICE_WORKER_ENABLED', true),
    'updates_enabled' => env('PWA_UPDATES_ENABLED', true),
    'outbox_enabled' => env('PWA_OUTBOX_ENABLED', true),
];
