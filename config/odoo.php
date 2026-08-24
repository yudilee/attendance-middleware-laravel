<?php

return [
    'enabled' => env('ODOO_SYNC_ENABLED', false),
    'url' => env('ODOO_URL', 'https://odoo.hartonomotor-group.com'),
    'db' => env('ODOO_DB', 'odoo_production'),
    'username' => env('ODOO_USERNAME', ''),
    'password' => env('ODOO_PASSWORD', ''),
    'sync_interval' => env('ODOO_SYNC_INTERVAL', 15), // minutes
];
