<?php

/** @var array $data */
/** Resolve current path for active link */
$currentPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$active = function (string $href) use ($currentPath): string {
    // active si égal ou préfixe (utile pour /docs/...)
    if ($href === '/') {
        return $currentPath === '/' ? 'active' : '';
    }
    return str_starts_with($currentPath, rtrim($href, '/')) ? 'active' : '';
};
?>

<?php /** views/partials/header.php */ ?>
<header class="nav" data-header>
    <div class="container nav-row">
        <a class="nav-brand" href="/">
            <img src="<?= asset('assets/logo/ivi.png') ?>" alt="ivi.php logo" width="26" height="26">
            <span>ivi.php</span>
        </a>

        <nav class="nav-links">
            <a href="/">Home</a>
            <a href="/docs">Docs</a>
            <a href="/guide">Guide</a>
            <a href="/examples">Examples</a>
            <a href="https://github.com/iviphp/ivi" target="_blank" rel="noopener">GitHub</a>
        </nav>

        <span class="nav-pill"><?= htmlspecialchars($_ENV['IVI_VERSION'] ?? 'v0.1.0 • DEV') ?></span>
    </div>
</header>