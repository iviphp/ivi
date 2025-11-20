<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= isset($title) ? htmlspecialchars($title) : 'ivi.php' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <?= $meta ?? '' ?>

    <!-- Favicon -->
    <link rel="icon" href="<?= $favicon ?? asset('assets/favicon/favicon.png') ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars($__csrf_token) ?>">
    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous" />

    <!-- Global CSS -->
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/modal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- Page-level CSS -->
    <?= $styles ?? '' ?>

    <meta name="theme-color" content="#008037">

    <!-- Optional: custom dark mode support -->
    <script>
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>

<body class="bg-light text-dark font-sans">

    <!-- Header -->
    <?php include base_path('views/partials/header.php'); ?>

    <!-- SPA container -->
    <main id="app" class="min-vh-100 p-4 container"
        data-title="<?= htmlspecialchars($params['title'] ?? $title ?? 'Softadastra') ?>">
        <?= $content ?? '' ?>
    </main>

    <!-- Footer -->
    <?php include base_path('views/partials/footer.php'); ?>

    <!-- Bootstrap JS Bundle (with Popper) -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
        defer></script>

    <!-- Global JS -->
    <script src="<?= asset('assets/js/spa.js') ?>" defer></script>
    <script src="<?= asset('assets/js/app.js') ?>" defer></script>
    <script src="<?= asset('assets/js/modal.js') ?>" defer></script>

    <!-- SPA toggle -->
    <script>
        window.__SPA__ = <?= isset($noSpa) && $noSpa ? 'false' : 'true' ?>;
    </script>

    <!-- Page-level JS -->
    <?= $scripts ?? '' ?>

</body>

</html>