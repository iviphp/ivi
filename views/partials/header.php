<header class="nav py-3 bg-light shadow-sm" data-header>
    <div class="container nav-row">

        <!-- Brand -->
        <a class="nav-brand d-flex align-items-center text-decoration-none" href="/" data-spa>
            <img src="<?= asset('assets/logo/ivi.png') ?>" alt="ivi.php logo" width="26" height="26" class="me-2">
            <span class="fw-bold fs-5 text-dark">ivi.php</span>
        </a>

        <!-- Nav links -->
        <nav class="collapse d-md-flex justify-content-center" id="navMenu">
            <?= menu([
                '/'       => 'Home',
                '/docs'   => 'Docs',
                '/users'  => 'Users',
            ], ['class' => 'nav-links d-flex flex-column flex-md-row gap-3 my-2 my-md-0']) ?>
        </nav>

        <!-- Right side: burger + theme + version -->
        <div class="nav-right d-flex align-items-center gap-2">
            <button class="theme-toggle btn btn-sm"
                type="button"
                data-theme-toggle
                aria-label="Toggle dark mode"
                title="Toggle theme">
                <span class="theme-ico" aria-hidden="true">
                    <i class="fas fa-moon" data-theme-icon></i>
                </span>
            </button>

            <span class="nav-pill badge">
                <?= htmlspecialchars($_ENV['IVI_VERSION'] ?? 'v0.1.0 • DEV') ?>
            </span>
            <button class="btn btn-sm btn-outline-secondary d-md-none"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navMenu"
                aria-controls="navMenu"
                aria-expanded="false"
                aria-label="Toggle navigation">☰</button>


        </div>

    </div>
</header>