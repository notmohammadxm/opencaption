<?php
declare(strict_types=1);

/**
 * Copy this file to config.php and fill in the database details from cPanel.
 * Never commit config.php or real API keys.
 */
return [
    'app_name' => 'OpenCaption',
    'base_url' => '', // Optional, e.g. https://example.com
    'timezone' => 'Asia/Tehran',
    'session_name' => 'opencaption_session',
    'database' => [
        'host' => 'localhost',
        'name' => 'CPANEL_DATABASE_NAME',
        'user' => 'CPANEL_DATABASE_USER',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'uploads' => [
        'directory' => __DIR__ . '/storage/uploads',
        'max_bytes' => 314572800, // 300 MiB; PHP/web-server limits must also allow this.
    ],
];
