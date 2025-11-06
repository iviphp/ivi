<?php

/** @var array $payload */ ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= (int)($payload['status'] ?? 500) ?> — <?= htmlspecialchars($payload['error'] ?? 'Error', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon/ivi-192x192.png">
    <style>
        body {
            margin: 0;
            background: #fafafa;
            color: #222;
            font: 16px/1.4 ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Arial
        }

        .wrap {
            max-width: 720px;
            margin: 8vh auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, .06);
            padding: 22px 26px
        }

        h1 {
            font-size: 20px;
            margin: 4px 0
        }

        p {
            opacity: .85
        }

        small {
            opacity: .6
        }
    </style>
</head>

<body>
    <div class="wrap">
        <h1><?= htmlspecialchars($payload['error'] ?? 'Error', ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($payload['message'] ?? 'An error occurred.', ENT_QUOTES, 'UTF-8') ?></p>
        <small>Status: <?= (int)($payload['status'] ?? 500) ?></small>
    </div>
</body>

</html>