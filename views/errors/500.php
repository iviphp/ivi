<?php

/** @var array $payload */ ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>500 — Server Error</title>
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
            opacity: .6;
            display: block;
            margin-top: 8px
        }

        ul {
            margin: 8px 0 0 18px
        }
    </style>
</head>

<body>
    <div class="wrap">
        <h1>500 — <?= htmlspecialchars($payload['error'] ?? 'Server Error', ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($payload['message'] ?? 'An unexpected error occurred.', ENT_QUOTES, 'UTF-8') ?></p>
        <small>Hint:
            <ul>
                <li>Check your database configuration in <code>.env</code></li>
                <li>Enable <code>APP_DEBUG=1</code> and <code>APP_ERROR_DETAIL=full</code> locally for a detailed stack.</li>
            </ul>
        </small>
    </div>
</body>

</html>