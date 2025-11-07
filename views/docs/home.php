<?php

/** views/docs/index.php — rendu dans base.php */
?>

<section class="docs-hero" role="region" aria-label="Documentation hero">
    <div class="container">
        <h1>Documentation</h1>
        <p class="lead">
            Learn how to build fast and expressive apps with <strong>ivi.php</strong>.
        </p>
        <div class="actions">
            <a href="#getting-started" class="btn">Get Started</a>
            <a href="#routing" class="btn secondary">Routing →</a>
        </div>
    </div>
</section>

<main class="docs-content container" id="content" role="main">

    <article id="getting-started" class="docs-section" aria-labelledby="h-getting-started">
        <h2 id="h-getting-started">Getting Started</h2>

        <p>Install the framework using Composer:</p>
        <div class="code-block">
            <button class="copy-btn" type="button" aria-label="Copy install command"
                data-copy="composer create-project iviphp/ivi myapp">Copy</button>
            <pre><code data-lang="bash">composer create-project iviphp/ivi myapp</code></pre>
        </div>

        <p>Then start the built-in PHP server:</p>
        <div class="code-block">
            <button class="copy-btn" type="button" aria-label="Copy PHP server command"
                data-copy="php -S localhost:8000 -t public">Copy</button>
            <pre><code data-lang="bash">php -S localhost:8000 -t public</code></pre>
        </div>
    </article>

    <article id="routing" class="docs-section" aria-labelledby="h-routing">
        <h2 id="h-routing">Routing</h2>
        <p>
            Define your routes in <code>config/routes.php</code> using a simple syntax:
        </p>
        <div class="code-block">
            <button class="copy-btn" type="button" aria-label="Copy routing example"
                data-copy="$router->get('/', [HomeController::class, 'home']);&#10;$router->get('/about', fn() => 'About Page');">
                Copy
            </button>
            <pre><code data-lang="php">$router->get('/', [HomeController::class, 'home']);
$router->get('/about', fn() =&gt; 'About Page');</code></pre>
        </div>
    </article>

    <article id="controllers" class="docs-section" aria-labelledby="h-controllers">
        <h2 id="h-controllers">Controllers</h2>
        <p>
            Controllers extend the <code>App\Controllers\Controller</code> base class
            and return a <code>Response</code> or <code>HtmlResponse</code>:
        </p>
        <div class="code-block">
            <button class="copy-btn" type="button" aria-label="Copy controller example"
                data-copy="class HomeController extends Controller {&#10;  public function home(Request $request): HtmlResponse {&#10;    return $this->view('welcome.home', ['title' => 'Welcome']);&#10;  }&#10;}">
                Copy
            </button>
            <pre><code data-lang="php">
                class HomeController extends Controller {
                    public function home(Request $request): HtmlResponse {
                    return $this->view('welcome.home', ['title' =&gt; 'Welcome']);
                    }
                }</code></pre>
        </div>
    </article>

</main>