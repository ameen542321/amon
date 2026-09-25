<?php

return [
    'token_lifetime_days' => (int) env('MOBILE_API_TOKEN_LIFETIME_DAYS', 30),
    'max_devices_per_account' => (int) env('MOBILE_API_MAX_DEVICES_PER_ACCOUNT', 10),
    'minimum_app_version' => env('MOBILE_API_MINIMUM_APP_VERSION', '1.0.0'),
];
