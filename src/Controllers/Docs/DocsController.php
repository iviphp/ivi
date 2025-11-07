<?php

namespace App\Controllers\Docs;

use App\Controllers\Controller;
use Ivi\Http\Request;
use Ivi\Http\HtmlResponse;
use Parsedown;

/**
 * Class DocsController
 *
 * Handles rendering of Markdown-based documentation pages
 * and applies syntax highlighting to code blocks.
 *
 * ### Responsibilities
 * - Load and parse `.md` documentation files using Parsedown.
 * - Wrap `<pre><code>` blocks in a custom `.code-block` container
 *   with a header bar, language badge, and copy button.
 * - Automatically detect the language (from `language-xxx`, `lang-xxx`,
 *   or PHP code detection fallback) and apply the correct class for Highlight.js.
 * - Return an HTML response that includes:
 *   - the rendered documentation content
 *   - `docs.css` styles for layout and syntax colors
 *   - Highlight.js for client-side syntax highlighting
 *
 * ### Notes
 * - The controller does **not** inject the `hljs` class manually;
 *   Highlight.js will handle that dynamically at runtime.
 * - The script uses the “build” version of Highlight.js, which bundles
 *   common languages for automatic detection.
 */
final class DocsController extends Controller
{
    public function index(Request $request): HtmlResponse
    {
        $file = docs_path('getting-started.md');

        if (!is_file($file)) {
            return new HtmlResponse('<h1>404 - Doc not found</h1>', 404);
        }

        // 1) Convert Markdown to HTML
        $parser = new Parsedown();
        $html   = $parser->text((string) file_get_contents($file));

        // 2) Enhance <pre><code> blocks: wrap with header + copy button + language badge
        $html = preg_replace_callback(
            '#<pre>\s*<code([^>]*)>(.*?)</code>\s*</pre>#si',
            function ($m) {
                $attrs = $m[1] ?? '';
                $code  = html_entity_decode($m[2] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

                // Detect the code language (from attributes or PHP opening tag)
                $lang = null;
                if (preg_match('/\blanguage-([a-z0-9+\-]+)\b/i', $attrs, $mm)) {
                    $lang = $mm[1];
                } elseif (preg_match('/\blang-([a-z0-9+\-]+)\b/i', $attrs, $mm)) {
                    $lang = $mm[1];
                } elseif (str_starts_with(trim($code), '<?php')) {
                    $lang = 'php';
                }

                // Inject or merge class="language-xxx" only (no "hljs")
                if ($lang) {
                    if (preg_match('/\bclass="([^"]*)"/i', $attrs, $cm)) {
                        $class = trim($cm[1] . ' language-' . $lang);
                        $attrs = preg_replace('/class="[^"]*"/i', 'class="' . htmlspecialchars($class, ENT_QUOTES) . '"', $attrs);
                    } else {
                        $attrs .= ' class="language-' . htmlspecialchars($lang, ENT_QUOTES) . '"';
                    }
                }

                // Re-encode code safely for HTML output
                $code  = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $badge = $lang ?: 'text';

                // Return enhanced HTML structure
                return <<<HTML
<div class="code-block">
  <div class="code-head">
    <div class="lang-badge">{$badge}</div>
    <button type="button" class="copy-btn" aria-label="Copy code to clipboard">Copy</button>
  </div>
  <pre><code {$attrs}>{$code}</code></pre>
</div>
HTML;
            },
            $html
        );

        // 3) Render the final documentation page
        return $this->view('docs.page', [
            'title'   => 'Docs — Getting Started',
            'content' => $html,
            'styles'  => '<link rel="stylesheet" href="' . asset('assets/css/docs.css') . '">',
            'scripts' => '
  <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/build/highlight.min.js"></script>
  <script>
    // Optional: restrict detection to common languages
    hljs.configure({languages: ["php","javascript","json","bash","html","css","ini","yaml"]});
    hljs.highlightAll();
  </script>
',
        ], $request);
    }
}
