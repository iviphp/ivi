<?php

/**
 * Logger intelligent : Web plein écran (auto thème) + CLI
 * Accent Ivi #ff9900
 *
 * @param mixed       $data   Données à afficher (string, array, object, etc.)
 * @param bool        $exit   Stopper le script après affichage
 * @param string|null $label  Titre/étiquette optionnelle (ex: "User payload")
 */
function logger($data, bool $exit = true, ?string $label = null): void
{
    $isCli = (php_sapi_name() === 'cli');

    // Infos & traces
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $caller    = $backtrace[0] ?? [];
    $file      = $caller['file'] ?? 'unknown';
    $line      = $caller['line'] ?? '??';
    $timestamp = date('Y-m-d H:i:s');

    // ====== CLI ======
    if ($isCli) {
        $cHdr = "\033[1;36m";  // cyan
        $cTxt = "\033[0;37m";  // gris clair
        $cAcc = "\033[38;5;208m"; // orange approx
        $cRes = "\033[0m";
        echo "{$cAcc}Softadastra Debug{$cRes} — {$cHdr}$file:$line [$timestamp]{$cRes}\n";
        if ($label) echo "{$cHdr}[$label]{$cRes}\n";

        if (is_array($data) || is_object($data)) {
            print_r($data);
        } elseif (is_bool($data)) {
            echo $data ? "true\n" : "false\n";
        } elseif (is_null($data)) {
            echo "null\n";
        } elseif (is_string($data) && ($j = json_decode($data, true)) !== null) {
            echo json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        } else {
            echo $cTxt . (string)$data . $cRes . PHP_EOL;
        }

        // Petite trace (max 5)
        $maxFrames = min(5, count($backtrace));
        echo "{$cHdr}Trace (top {$maxFrames}){$cRes}\n";
        for ($i = 0; $i < $maxFrames; $i++) {
            $f = $backtrace[$i];
            $fFile = $f['file'] ?? '[internal]';
            $fLine = $f['line'] ?? '-';
            $fFunc = $f['function'] ?? '';
            echo "  #$i $fFile:$fLine $fFunc()\n";
        }

        if ($exit) exit;
        return;
    }

    // ====== WEB ======
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }

    // Helpers (sécurisation + rendu)
    $escape = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $renderData = static function ($data) use ($escape) {
        if (is_array($data) || is_object($data)) {
            return $escape(print_r($data, true));
        }
        if (is_bool($data))   return $data ? 'true' : 'false';
        if (is_null($data))   return 'null';
        if (is_string($data)) {
            $json = json_decode($data, true);
            if ($json !== null) {
                return $escape(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
        return $escape((string)$data);
    };

    $traceHtml = (function (array $bt, callable $escape) {
        $max = min(5, count($bt));
        $out = '';
        for ($i = 0; $i < $max; $i++) {
            $f = $bt[$i];
            $fFile = $escape($f['file'] ?? '[internal]');
            $fLine = $escape($f['line'] ?? '-');
            $fFunc = $escape($f['function'] ?? '');
            $out  .= "<div class=\"frame\">#$i <span class=\"file\">$fFile</span>:<span class=\"line\">$fLine</span> <span class=\"func\">$fFunc()</span></div>";
        }
        return $out;
    })($backtrace, $escape);

    // Sortie HTML plein écran (auto-thème + accent Softadastra)
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Softadastra Debug</title>';
    echo '<style>
:root {
  --accent: #ff9900;
  --bg: #ffffff;
  --fg: #1f2328;
  --muted: #57606a;
  --panel: #ffffff;
  --panel-border: #e5e7eb;
  --code-bg: #fff8f0;  /* léger ton crème */
  --shadow: 0 8px 24px rgba(140,149,159,0.2);
}
@media (prefers-color-scheme: dark) {
  :root {
    --accent: #ff9900;
    --bg: #0f1115;
    --fg: #e6edf3;
    --muted: #9aa4ad;
    --panel: #0f141a;
    --panel-border: #1f252c;
    --code-bg: #1a140b; /* sombre avec nuance orange */
    --shadow: 0 8px 24px rgba(0,0,0,0.45);
  }
}
* { box-sizing: border-box; }
html, body { height: 100%; }
body {
  margin: 0;
  background: var(--bg);
  color: var(--fg);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, "Noto Sans", "Liberation Sans", sans-serif;
  display: flex; flex-direction: column; min-height: 100vh;
}
header {
  background: linear-gradient(90deg, var(--accent), #ffad33);
  color: #111;
  padding: 14px 22px;
  font-weight: 700;
  letter-spacing: .2px;
  display: flex; align-items: center; gap: 12px;
  position: sticky; top: 0; z-index: 10;
}
header .badge {
  background: #111; color: #fff; padding: 3px 8px; border-radius: 999px; font-size: 12px;
}
main {
  flex: 1;
  padding: clamp(16px, 2vw, 28px);
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}
.panel {
  background: var(--panel);
  border: 1px solid var(--panel-border);
  border-radius: 12px;
  box-shadow: var(--shadow);
  overflow: hidden;
}
.panel .panel-head {
  padding: 14px 16px;
  border-bottom: 1px solid var(--panel-border);
  display: flex; justify-content: space-between; align-items: center;
}
.panel .meta {
  font-size: 13px; color: var(--muted);
}
.panel .actions { display: flex; gap: 8px; }
button.copy {
  background: var(--accent); border: none; color: #111; font-weight: 600;
  padding: 8px 12px; border-radius: 8px; cursor: pointer;
}
button.copy:hover { filter: brightness(1.05); }
.panel .panel-body { padding: 0; }
pre.code {
  margin: 0;
  background: var(--code-bg);
  padding: 18px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
  font-size: 13.5px; line-height: 1.55;
  white-space: pre-wrap; word-break: break-word;
}
.subpanel { padding: 12px 16px; }
.trace .frame { font-family: ui-monospace, monospace; font-size: 13px; color: var(--muted); }
.trace .file { color: var(--fg); font-weight: 600; }
.trace .line { color: var(--accent); font-weight: 700; }
.trace .func { color: var(--muted); }
footer {
  border-top: 1px solid var(--panel-border);
  background: var(--panel);
  color: var(--muted);
  text-align: center;
  padding: 10px 12px;
}
h2 { margin: 0; font-size: 16px; }
    </style></head><body>';

    echo '<header>🧩 Softadastra Debug <span class="badge">Live</span></header>';

    echo '<main>';

    // Panneau données
    echo '<section class="panel">';
    echo '  <div class="panel-head">';
    echo '    <div class="meta">';
    echo '      <strong>' . ($label ? $escape($label) : 'Données') . '</strong>';
    echo '      <span>• ' . $escape(basename($file)) . ':' . $line . '</span>';
    echo '      <span>• ' . $escape($timestamp) . '</span>';
    echo '      <span>• PHP ' . $escape(PHP_VERSION) . '</span>';
    echo '    </div>';
    echo '    <div class="actions"><button class="copy" id="copyBtn">Copier</button></div>';
    echo '  </div>';
    echo '  <div class="panel-body">';
    $content = $renderData($data);
    echo '    <pre class="code" id="codeBlock">' . $content . '</pre>';
    echo '  </div>';
    echo '</section>';

    // Panneau trace
    echo '<section class="panel">';
    echo '  <div class="panel-head"><h2>Trace d’appel (top 5)</h2></div>';
    echo '  <div class="subpanel trace">' . $traceHtml . '</div>';
    echo '</section>';

    echo '</main>';

    echo '<footer>Softadastra Debug Console — &copy; ' . date('Y') . ' — Accent <strong>#ff9900</strong></footer>';

    echo '<script>
const btn = document.getElementById("copyBtn");
const code = document.getElementById("codeBlock");
btn?.addEventListener("click", async () => {
  try {
    const txt = code?.innerText || "";
    await navigator.clipboard.writeText(txt);
    btn.textContent = "Copié ✓";
    setTimeout(() => (btn.textContent = "Copier"), 1200);
  } catch (e) {
    btn.textContent = "Impossible de copier";
    setTimeout(() => (btn.textContent = "Copier"), 1500);
  }
});
</script>';

    echo '</body></html>';

    if ($exit) exit;
}
