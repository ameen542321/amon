<?php

return [
    'version' => (string) env('DESIGN_SYSTEM_VERSION', '1'),
    'default_theme' => 'dark',
    'supported_themes' => ['dark', 'light'],
    'direction' => 'rtl',
    'locale' => 'ar',
    'font' => [
        'family' => 'Cairo',
        'fallbacks' => ['Cairo Legacy', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        'asset' => '/fonts/cairo/cairo-arabic-wght-normal.woff2',
        'weights' => ['min' => 200, 'max' => 1000],
    ],
    'brand' => [
        'name' => 'CARLED',
        'logo' => '/carled.svg',
        'manifest' => '/manifest.webmanifest',
    ],
    'themes' => [
        'dark' => [
            'brand' => '#00C4B4', 'brand_strong' => '#05d8c7', 'background' => '#020617',
            'surface' => '#0f172a', 'surface_muted' => '#111827', 'surface_strong' => '#1e293b',
            'text' => '#f8fafc', 'text_soft' => '#cbd5e1', 'text_muted' => '#94a3b8',
            'border' => '#334155', 'border_soft' => '#1e293b',
            'success_background' => 'rgba(16, 185, 129, .13)', 'success_text' => '#6ee7b7',
            'warning_background' => '#1e293b', 'warning_text' => '#f59e0b',
            'danger_background' => 'rgba(185, 28, 28, .12)', 'danger_text' => '#ef4444',
            'info_background' => 'rgba(21, 94, 117, .2)', 'info_text' => '#22d3ee',
        ],
        'light' => [
            'brand' => '#00C4B4', 'brand_strong' => '#00AFA2', 'background' => '#f3f6fb',
            'surface' => '#ffffff', 'surface_muted' => '#f8fafc', 'surface_strong' => '#eef3f8',
            'text' => '#0f172a', 'text_soft' => '#1e293b', 'text_muted' => '#475569',
            'border' => '#dbe3ed', 'border_soft' => '#e2e8f0',
            'success_background' => '#dcfce7', 'success_text' => '#166534',
            'warning_background' => '#eef3f8', 'warning_text' => '#92400e',
            'danger_background' => '#fee2e2', 'danger_text' => '#b91c1c',
            'info_background' => '#eef3f8', 'info_text' => '#1e3a8a',
        ],
    ],
    'status_dots' => ['success' => '#22c55e', 'warning' => '#facc15', 'danger' => '#ef4444'],
];
