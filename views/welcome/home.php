<?php

/**
 * views/welcome/home.php
 * Rendered inside base.php
 */
?>

<header class="nav" data-header>
    <div class="container nav-row">
        <a class="nav-brand" href="/">
            <img src="<?= asset('assets/logo/ivi.png') ?>" alt="ivi.php logo" width="26" height="26">
            <span>ivi.php</span>
        </a>

        <nav class="nav-links">
            <a href="/docs">Docs</a>
            <a href="/guide">Guide</a>
            <a href="/examples">Examples</a>
            <a href="https://github.com/iviphp/ivi" target="_blank" rel="noopener">GitHub</a>
        </nav>

        <span class="nav-pill">v0.1.0 • DEV</span>
    </div>
</header>

<section class="hero">
    <div class="container">
        <div class="badges">
            <span class="badge">Simple</span>
            <span class="dot"></span>
            <span class="badge">Modern</span>
            <span class="dot"></span>
            <span class="badge">Expressive</span>
        </div>

        <h1>Build delightful PHP apps with clarity and speed.</h1>
        <p>
            ivi.php is a lightweight, modern framework that favors developer joy,
            expressive APIs and production-grade performance — without the bloat.
        </p>

        <div class="actions">
            <a class="btn btn-lg" href="/docs" rel="noopener">Get Started</a>
            <a class="btn secondary btn-lg" href="https://github.com/iviphp/ivi" target="_blank" rel="noopener">
                View on GitHub
            </a>
        </div>

        <div class="install">
            <code id="install">composer create-project iviphp/ivi myapp</code>
            <button class="copy" data-copy="#install" aria-label="Copy install command">Copy</button>
        </div>

        <div class="hero-blob" aria-hidden="true"></div>
    </div>
</section>

<section class="section features">
    <div class="container">
        <div class="grid cols-3">
            <article class="card">
                <div class="icon">⚙️</div>
                <h3>Minimal Core</h3>
                <p>Clear building blocks: App, Router, Request, Response, Middleware.</p>
            </article>
            <article class="card">
                <div class="icon">✨</div>
                <h3>Expressive by Design</h3>
                <p>Readable APIs that let your intent shine through.</p>
            </article>
            <article class="card">
                <div class="icon">⚡</div>
                <h3>Performance First</h3>
                <p>Lean runtime, zero-nonsense abstractions — built to ship fast.</p>
            </article>
        </div>
    </div>
</section>

<section class="section eco">
    <div class="container">
        <h2>Ecosystem</h2>
        <p class="muted">A growing set of tools to help you ship faster.</p>
        <div class="eco-grid">
            <a class="eco-card" href="/router">
                <div class="eco-title">Router</div>
                <div class="eco-desc">Elegant route definitions & middleware.</div>
            </a>
            <a class="eco-card" href="/orm">
                <div class="eco-title">ORM</div>
                <div class="eco-desc">Clean models, query builder, pagination.</div>
            </a>
            <a class="eco-card" href="/cli">
                <div class="eco-title">CLI</div>
                <div class="eco-desc">Migrations & dev tooling that feels right.</div>
            </a>
        </div>
    </div>
</section>

<footer>
    <div class="foot">
        © <span id="y"></span> ivi.php — Part of the Softadastra ecosystem.
        <span class="foot-links">
            <a class="link" href="https://github.com/iviphp/ivi" target="_blank" rel="noopener">GitHub</a>
            &nbsp;•&nbsp;
            <a class="link" href="/license" rel="noopener">MIT License</a>
        </span>
    </div>
</footer>