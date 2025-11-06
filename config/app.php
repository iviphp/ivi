<?php
return [
    'debug'        => (($_ENV['APP_DEBUG'] ?? '0') === '1'),
    'env'          => $_ENV['APP_ENV'] ?? 'production',
    'error_detail' => $_ENV['APP_ERROR_DETAIL'] ?? 'full', // none|safe|full

    // Branding
    'brand'      => $_ENV['APP_BRAND'] ?? 'softadastra',
    'theme'      => $_ENV['APP_THEME'] ?? 'dark',
    'brand_logo' => $_ENV['APP_BRAND_LOGO'] ?? '/assets/logo/ivi.png',
];
