<!-- <h1><?= htmlspecialchars($title ?? 'Home') ?></h1>

<ul style="background-color: #008037;
">
    <?php foreach (($products ?? []) as $p): ?>
        <li>#<?= (int)$p['id'] ?> — <?= htmlspecialchars($p['name']) ?></li>
    <?php endforeach; ?>
</ul> -->

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>ivi.php — Simple. Modern. Expressive.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <style>
        :root {
            --accent: #008037;
            /* Vert officiel ivi.php */
            --bg: #ffffff;
            --fg: #111111;
            --muted: #555555;
            --panel: #ffffff;
            --border: #e5e7eb;
            --code: #f8fff9;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1115;
                --fg: #e6edf3;
                --muted: #9aa4ad;
                --panel: #0f141a;
                --border: #1f252c;
                --code: #122016;
            }
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            height: 100%
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--fg);
            font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif;
            line-height: 1.5;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: var(--accent);
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 14px 20px;
        }

        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px
        }

        .brand img {
            height: 26px;
            width: auto;
            display: block
        }

        .brand .name {
            font-weight: 700;
            letter-spacing: .3px
        }

        .pill {
            background: #fff;
            color: var(--accent);
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 12px;
        }

        /* Hero */
        .hero {
            padding: 56px 20px 32px;
            background:
                radial-gradient(1200px 600px at 50% -10%, rgba(0, 128, 55, .10), transparent 60%),
                linear-gradient(#fff, #fff);
            color: var(--fg);
        }

        @media (prefers-color-scheme: dark) {
            .hero {
                background:
                    radial-gradient(1200px 600px at 50% -10%, rgba(0, 128, 55, .2), transparent 60%),
                    linear-gradient(#0f1115, #0f1115);
            }
        }

        .hero h1 {
            font-size: clamp(28px, 4vw, 44px);
            line-height: 1.12;
            margin: 0 0 10px;
            font-weight: 800;
            letter-spacing: .2px;
        }

        .hero p {
            margin: 0 0 18px;
            color: var(--muted);
            max-width: 750px;
            font-size: clamp(15px, 2vw, 18px);
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px
        }

        .btn {
            display: inline-block;
            border: 1px solid var(--accent);
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 700;
        }

        .btn.secondary {
            background: transparent;
            color: var(--accent);
            border: 1px solid var(--accent);
        }

        .tagline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #fff;
            background: var(--accent);
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 12px;
            margin-bottom: 14px;
        }

        /* Code block */
        pre {
            background: var(--code);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            margin: 22px 0 0;
            overflow: auto;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13.5px;
        }

        .copy {
            border: none;
            background: var(--accent);
            color: #fff;
            padding: 8px 10px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            margin-left: 8px;
        }

        /* Features */
        .section {
            padding: 26px 20px
        }

        .grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(12, 1fr);
        }

        .cols-3>* {
            grid-column: span 12
        }

        @media(min-width:760px) {
            .cols-3>* {
                grid-column: span 4
            }
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
        }

        .card h3 {
            margin: 0 0 6px;
            font-size: 18px
        }

        .card p {
            margin: 0;
            color: var(--muted)
        }

        /* Footer */
        footer {
            margin-top: auto;
            border-top: 1px solid var(--border);
            background: var(--panel);
            color: var(--muted);
        }

        footer .foot {
            max-width: 1100px;
            margin: 0 auto;
            padding: 14px 20px;
            font-size: 14px
        }

        a.link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 700
        }
    </style>
</head>

<body>

    <!-- Header -->
    <div class="header">
        <div class="container">
            <div class="row">
                <div class="brand">
                    <!-- Logo blanc sur fond vert -->
                    <img src="/assets/logo/ivi.png" alt="ivi.php logo">
                    <span class="name">ivi.php</span>
                </div>
                <span class="pill">v0.1.0 • DEV</span>
            </div>
        </div>
    </div>

    <!-- Hero -->
    <section class="hero">
        <div class="container">
            <span class="tagline">Simple • Modern • Expressive</span>
            <h1>Build delightful PHP apps with clarity and speed.</h1>
            <p>
                ivi.php is a lightweight, modern framework that favors developer joy,
                expressive APIs and production-grade performance — without the bloat.
            </p>

            <div class="actions">
                <a class="btn" href="https://github.com/iviphp/ivi" target="_blank" rel="noopener">Get started</a>
                <a class="btn secondary" href="/docs" rel="noopener">Documentation</a>
            </div>

            <pre><code id="install">composer create-project iviphp/ivi myapp</code> <button class="copy" data-copy="#install">Copy</button></pre>
        </div>
    </section>

    <!-- Features -->
    <section class="section">
        <div class="container">
            <div class="grid cols-3">
                <div class="card">
                    <h3>Minimal Core</h3>
                    <p>Clear building blocks: App, Router, Request, Response, Middleware.</p>
                </div>
                <div class="card">
                    <h3>Expressive by Design</h3>
                    <p>Beautiful, readable APIs that let your intent shine through.</p>
                </div>
                <div class="card">
                    <h3>Performance First</h3>
                    <p>Lean runtime, zero-nonsense abstractions—built to ship fast.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="foot">
            © <span id="y"></span> ivi.php — Part of the Softadastra ecosystem.
            <span style="float:right">
                <a class="link" href="https://github.com/iviphp/ivi" target="_blank" rel="noopener">GitHub</a>
                &nbsp;•&nbsp;
                <a class="link" href="/license" rel="noopener">MIT License</a>
            </span>
        </div>
    </footer>

    <script>
        // year + copy button
        document.getElementById('y').textContent = new Date().getFullYear();
        document.querySelectorAll('.copy').forEach(btn => {
            btn.addEventListener('click', async () => {
                const sel = btn.getAttribute('data-copy');
                const el = document.querySelector(sel);
                const txt = el ? el.textContent.trim() : '';
                try {
                    await navigator.clipboard.writeText(txt);
                    btn.textContent = 'Copied ✓';
                } catch (_) {
                    btn.textContent = 'Unable to copy';
                }
                setTimeout(() => btn.textContent = 'Copy', 1200);
            });
        });
    </script>
</body>

</html>