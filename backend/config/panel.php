<?php

return [
    'services' => array_values(array_filter(array_map('trim', explode(',', env(
        'PANEL_SERVICES',
        'nginx,php8.4-fpm,mariadb,tailscaled,ngrok'
    ))))),
    'mounts' => array_values(array_filter(array_map('trim', explode(',', env('PANEL_MOUNTS', '/'))))),
    'proc_path' => env('PANEL_PROC_PATH', '/proc'),
    'sys_path' => env('PANEL_SYS_PATH', '/sys'),
    'systemctl_enabled' => (bool) env('PANEL_SYSTEMCTL_ENABLED', true),
    'systemctl_binary' => env('PANEL_SYSTEMCTL_BINARY', '/usr/bin/systemctl'),
    'stale_after_seconds' => (int) env('PANEL_STALE_AFTER_SECONDS', 180),
    'http_degraded_ms' => (int) env('PANEL_HTTP_DEGRADED_MS', 1500),
    'lock_timeout_seconds' => (int) env('PANEL_LOCK_TIMEOUT_SECONDS', 60),
    'allowed_check_hosts' => array_values(array_filter(array_map('trim', explode(',', env(
        'PANEL_ALLOWED_CHECK_HOSTS',
        '127.0.0.1,localhost'
    ))))),
    'server_name' => env('PANEL_SERVER_NAME', 'Servidor principal'),
    'server_hostname' => env('PANEL_SERVER_HOSTNAME', gethostname() ?: 'localhost'),
];
