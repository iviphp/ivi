<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= isset($title) ? htmlspecialchars($title) : 'ivi.php' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <?= $meta ?? '' ?>

    <!-- Global CSS -->
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <!-- Page-level CSS (optional) -->
    <?= $styles ?? '' ?>
</head>

<body>
    <main id="app">
        <?= $content ?? '' ?>
    </main>

    <!-- Global JS -->
    <script src="<?= asset('assets/js/app.js') ?>" defer></script>
    <!-- Page-level JS (optional) -->
    <?= $scripts ?? '' ?>
</body>

</html>