<?php
return [
    'debug'        => (($_ENV['APP_DEBUG'] ?? '0') === '1'),
    'env'          => $_ENV['APP_ENV'] ?? 'production',
    'error_detail' => $_ENV['APP_ERROR_DETAIL'] ?? 'safe', // none|safe|full
];
