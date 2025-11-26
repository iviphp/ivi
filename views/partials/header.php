<header class="nav py-3 bg-light shadow-sm" data-header>
    <div class="container d-flex justify-content-between align-items-center flex-wrap">

        <!-- Brand -->
        <a class="nav-brand d-flex align-items-center text-decoration-none" href="/" data-spa>
            <img src="<?= asset('assets/logo/ivi.png') ?>" alt="ivi.php logo" width="26" height="26" class="me-2">
            <span class="fw-bold fs-5 text-dark">ivi.php</span>
        </a>

        <!-- Menu toggle for mobile -->
        <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
            ☰
        </button>

        <!-- Nav links -->
        <nav class="collapse d-md-flex justify-content-center flex-grow-1" id="navMenu">
            <?= menu([
                '/'        => 'Home',
                '/docs'    => 'Docs',
                '/users'   => 'Users',
                '/auth'    => 'Auth',
                '/market' => 'Market Example'
            ], ['class' => 'nav-links d-flex flex-column flex-md-row gap-3 my-2 my-md-0']) ?>
        </nav>

        <!-- Version pill -->
        <span class="nav-pill badge bg-secondary text-light mt-2 mt-md-0">
            <?= htmlspecialchars($_ENV['IVI_VERSION'] ?? 'v0.1.0 • DEV') ?>
        </span>
    </div>
</header>