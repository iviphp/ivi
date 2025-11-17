<?php

/** views/welcome/home.php — rendu dans base.php */
?>
<section class="hero py-5 bg-light text-dark">
    <div class="container text-center">
        <!-- Badges -->
        <div class="mb-3 d-flex justify-content-center align-items-center flex-wrap gap-2">
            <span class="badge bg-primary">Simple</span>
            <span class="text-muted">•</span>
            <span class="badge bg-success">Modern</span>
            <span class="text-muted">•</span>
            <span class="badge bg-warning text-dark">Expressive</span>
        </div>

        <h1 class="display-4 fw-bold mb-3">Build delightful PHP apps with clarity and speed.</h1>
        <p class="lead mb-4">
            ivi.php is a lightweight, modern framework that favors developer joy,
            expressive APIs, and production-grade performance — without the bloat.
        </p>

        <div class="mb-4 d-flex justify-content-center gap-3 flex-wrap">
            <a class="btn btn-lg btn-primary" href="/docs" rel="noopener" data-spa>Get Started</a>
            <a class="btn btn-lg btn-outline-secondary" href="https://github.com/iviphp/ivi" target="_blank" rel="noopener">View on GitHub</a>
        </div>

        <div class="d-inline-flex align-items-center bg-light border rounded px-3 py-2 mb-5">
            <code id="install" class="me-2">composer create-project iviphp/ivi myapp</code>
            <button class="btn btn-sm btn-outline-primary" data-copy="#install" aria-label="Copy install command">Copy</button>
        </div>

        <div class="hero-blob position-absolute top-0 start-50 translate-middle opacity-25" aria-hidden="true"></div>
    </div>
</section>

<section class="section features py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 shadow-sm p-3 text-center">
                    <div class="fs-1 mb-2">⚙️</div>
                    <h3 class="h5 fw-bold">Minimal Core</h3>
                    <p class="mb-0">Clear building blocks: App, Router, Request, Response, Middleware.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm p-3 text-center">
                    <div class="fs-1 mb-2">✨</div>
                    <h3 class="h5 fw-bold">Expressive by Design</h3>
                    <p class="mb-0">Readable APIs that let your intent shine through.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm p-3 text-center">
                    <div class="fs-1 mb-2">⚡</div>
                    <h3 class="h5 fw-bold">Performance First</h3>
                    <p class="mb-0">Lean runtime, zero-nonsense abstractions — built to ship fast.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section ecosystem py-5 bg-light">
    <div class="container text-center">
        <h2 class="fw-bold mb-2">Ecosystem</h2>
        <p class="text-muted mb-4">A growing set of tools to help you ship faster.</p>

        <div class="row g-4 justify-content-center">
            <div class="col-md-4">
                <a class="card h-100 shadow-sm p-3 text-decoration-none text-dark" href="/router">
                    <div class="h5 fw-bold">Router</div>
                    <p class="mb-0 text-muted">Elegant route definitions & middleware.</p>
                </a>
            </div>
            <div class="col-md-4">
                <a class="card h-100 shadow-sm p-3 text-decoration-none text-dark" href="/orm">
                    <div class="h5 fw-bold">ORM</div>
                    <p class="mb-0 text-muted">Clean models, query builder, pagination.</p>
                </a>
            </div>
            <div class="col-md-4">
                <a class="card h-100 shadow-sm p-3 text-decoration-none text-dark" href="/cli">
                    <div class="h5 fw-bold">CLI</div>
                    <p class="mb-0 text-muted">Migrations & dev tooling that feels right.</p>
                </a>
            </div>
        </div>
    </div>
</section>