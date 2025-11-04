<?php
$errorMessage = $params['errorMessage'] ?? null;
$statusCode   = $params['statusCode'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <title>Softadastra — Error</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="robots" content="noindex" />
  <link rel="stylesheet" href="<?= CSS_PATH ?>errors/styles.css">
</head>

<body>
  <section class="sa-error-wrap" role="main" aria-labelledby="err-title">
    <div class="sa-error-card">
      <div class="sa-error-head">
        <div class="sa-error-illustration" aria-hidden="true">
          <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Softadastra">
            <defs>
              <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#ffb84d" />
                <stop offset="100%" stop-color="#ff9900" />
              </linearGradient>
            </defs>
            <path d="M32 6l18 6v10c0 12.6-7.5 24.2-18 29-10.5-4.8-18-16.4-18-29V12l18-6z" fill="url(#g)" />
            <path d="M22 29h20M24 36h16" stroke="#fff" stroke-width="3" stroke-linecap="round" />
          </svg>
        </div>
        <div>
          <h1 id="err-title" class="sa-error-title">
            <?php
            $code = htmlspecialchars($statusCode ?? 'Error');
            echo ($code === '404') ? 'Page not found' : (($code === '403') ? 'Access denied' : 'Something went wrong');
            ?>
          </h1>
          <p class="sa-error-sub">
            <span class="sa-code">Code: <?= $code ?></span>
            <span class="sa-status">
              <?php if (!empty($errorMessage)): ?>
                <?= htmlspecialchars($errorMessage) ?>
              <?php else: ?>
                We couldn’t complete your request.
              <?php endif; ?>
            </span>
          </p>
        </div>
      </div>

      <div class="sa-error-body">
        <div class="sa-section sa-help">
          <div class="sa-row">
            <strong style="color:var(--sa-ink)">Let’s get you back on track</strong>
            <div class="sa-actions">
              <button class="sa-btn" type="button" onclick="saGoBack()">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Go back
              </button>
              <a class="sa-btn sa-btn--ghost" href="/">
                <i class="fas fa-home" aria-hidden="true"></i> Home
              </a>
            </div>
          </div>
          <div class="sa-row">
            <label for="sa-q"><strong>Search Softadastra</strong></label>
            <div class="sa-search">
              <input id="sa-q" class="sa-input" type="search" placeholder="Try 'men shoes', 'electronics', 'seller center'…" />
              <button class="sa-btn" type="button" onclick="saSearch()">Search</button>
            </div>
          </div>
          <div class="sa-row">
            <div class="sa-links" aria-label="Quick links">
              <a class="sa-link" href="/categories">Browse categories</a>
              <a class="sa-link" href="/help">Help Center</a>
            </div>
          </div>
        </div>
        <aside class="sa-section">
          <strong style="color:var(--sa-ink)">Technical details</strong>
          <div class="sa-meta" style="margin-top:8px">
            <div>Status code: <code><?= $code ?></code></div>
            <?php if (!empty($requestId)): ?>
              <div>Request ID: <code><?= htmlspecialchars($requestId) ?></code></div>
            <?php endif; ?>
            <div>Time: <code><?= date('Y-m-d H:i:s') ?></code></div>
            <?php if (!empty($_SERVER['REQUEST_URI'])): ?>
              <div>URL: <code><?= htmlspecialchars($_SERVER['REQUEST_URI']) ?></code></div>
            <?php endif; ?>
            <div>If this keeps happening, <a class="sa-link" href="/help/contact">contact support</a>.</div>
          </div>
        </aside>
      </div>
    </div>
  </section>

  <script>
    function saGoBack() {
      if (document.referrer) history.back();
      else window.location.href = "/";
    }

    function saSearch() {
      const q = (document.getElementById("sa-q")?.value || "").trim();
      if (!q) return;
      window.location.href = "/search?q=" + encodeURIComponent(q);
    }
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</body>

</html>