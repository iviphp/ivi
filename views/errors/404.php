<?php

/** @var array $payload ex: ['error'=>..., 'message'=>...] */ ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>404 — Not Found</title>
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

        a.btn {
            display: inline-block;
            margin-top: 12px;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            text-decoration: none;
            color: #111
        }
    </style>
</head>

<body>
    <div class="wrap">
        <h1>404 — <?= htmlspecialchars($payload['error'] ?? 'Not Found', ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($payload['message'] ?? 'The requested resource was not found.', ENT_QUOTES, 'UTF-8') ?></p>
        <a class="btn" href="/">← Go home</a>
        <br><small>Tip: check your route or controller action.</small>
    </div>
</body>

</html>