<?php

/** views/docs/index.php — rendu dans base.php */
?>
<section class="docs-hero">
    <div class="container">
        <h1>Documentation</h1>
        <p class="lead">Learn how to build fast and expressive apps with <strong>ivi.php</strong>.</p>
        <div class="actions">
            <a href="#getting-started" class="btn">Get Started</a>
            <a href="#routing" class="btn secondary">Routing →</a>
        </div>
    </div>
</section>

<main class="docs-content container">
    <article id="getting-started" class="docs-section">
        <h2>Getting Started</h2>
        <p>Install the framework using Composer:</p>
        <pre><code>composer create-project iviphp/ivi myapp</code></pre>

        <p>Then start the built-in PHP server:</p>
        <pre><code>php -S localhost:8000 -t public</code></pre>
    </article>

    <article id="routing" class="docs-section">
        <h2>Routing</h2>
        <p>Define your routes in <code>config/routes.php</code> using a simple syntax:</p>
        <pre><code>$router->get('/', [HomeController::class, 'home']);
$router->get('/about', fn() => 'About Page');</code></pre>
    </article>

    <article id="controllers" class="docs-section">
        <h2>Controllers</h2>
        <p>Controllers extend the <code>App\Controllers\Controller</code> base class and return a <code>Response</code> or <code>HtmlResponse</code>:</p>
        <pre><code>class HomeController extends Controller {
  public function home(Request $request): HtmlResponse {
    return $this->view('welcome.home', ['title' => 'Welcome']);
  }
}</code></pre>
    </article>
</main>