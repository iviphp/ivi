<?php

/**
 * -----------------------------------------------------------------------------
 * View: market::home
 * -----------------------------------------------------------------------------
 *
 * Default homepage view for the **Market/Core** module.
 * This template demonstrates how a module can integrate seamlessly into the
 * Ivi Framework's modular system — using its own view namespace, route, and
 * isolated configuration context.
 *
 * ## Responsibilities
 * - Display the module’s title (injected from the controller).
 * - Confirm that the module was correctly loaded and registered.
 * - Serve as an example template for developers building custom modules.
 *
 * ## Design Notes
 * - Uses the global `e()` helper for safe HTML escaping.
 * - The `$title` variable is dynamically passed from `HomeController@index()`.
 * - Developers can freely customize or disable this module, as the Ivi
 *   framework allows optional modular activation.
 *
 * @package  Market\Core\Views
 * @category Views
 * @version  1.0.0
 * @since    Ivi Framework v1.1
 */
?>
<?php /** @var string $title */ ?>
<!—- Market/Core —->
    <section class="mk-hero">
        <div class="mk-hero-inner">
            <img class="mk-logo" src="<?= module_asset('Market/Core', 'softadastra-market.png') ?>" alt="Softadastra Market">
            <h1 class="mk-title"><?= e($title ?? 'Softadastra Market') ?></h1>
            <p class="mk-subtitle">Cross-border commerce · Modules ivi.php · Ultra-rapide</p>

            <div class="mk-cta">
                <a class="mk-btn mk-btn-primary" href="/market">Explorer le marché</a>
                <a class="mk-btn mk-btn-ghost" href="/market/ping">Ping module</a>
            </div>

            <div class="mk-badges">
                <span class="mk-badge">Market/Core</span>
                <span class="mk-badge">Modules ivi</span>
                <span class="mk-badge">SPA-ready</span>
            </div>
        </div>
    </section>

    <section class="mk-section mk-kpis">
        <div class="mk-wrap">
            <div class="mk-card">
                <div class="mk-card-kpi">1 284</div>
                <div class="mk-card-label">Produits actifs</div>
            </div>
            <div class="mk-card">
                <div class="mk-card-kpi">312</div>
                <div class="mk-card-label">Vendeurs vérifiés</div>
            </div>
            <div class="mk-card">
                <div class="mk-card-kpi">4.8★</div>
                <div class="mk-card-label">Satisfaction</div>
            </div>
        </div>
    </section>

    <section class="mk-section">
        <div class="mk-wrap mk-grid">
            <article class="mk-feature">
                <h3>Catalogues rapides</h3>
                <p>Listes paginées, filtres dynamiques et images optimisées.</p>
                <a class="mk-link" href="/market">Voir les catégories →</a>
            </article>
            <article class="mk-feature">
                <h3>Panier & Paiements</h3>
                <p>Flux d’achat simple, paiements locaux, statuts de commande.</p>
                <a class="mk-link" href="/market">Démarrer un panier →</a>
            </article>
            <article class="mk-feature">
                <h3>Suivi & Notifs</h3>
                <p>Suivre des boutiques, alertes prix et reprise de panier.</p>
                <a class="mk-link" href="/market">Activer les alertes →</a>
            </article>
        </div>
    </section>

    <section class="mk-section mk-banner" id="market-banner">
        <div class="mk-wrap">
            <p>✅ Le style du module <strong>Market/Core</strong> est bien chargé via <code>assets/css/style.css</code>.</p>
            <p>URL test favicon : <code>/modules/Market/Core/softadastra-market.png</code></p>
        </div>
    </section>

    <section class="mk-section">
        <div class="mk-wrap mk-table-wrap">
            <h2>Dernières activités (démo)</h2>
            <table class="mk-table">
                <thead>
                    <tr>
                        <th>Événement</th>
                        <th>Objet</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Produit ajouté</td>
                        <td>#A192 • Phone Pro</td>
                        <td>09/11/2025</td>
                    </tr>
                    <tr>
                        <td>Nouvelle boutique</td>
                        <td>Tech-UG Kampala</td>
                        <td>08/11/2025</td>
                    </tr>
                    <tr>
                        <td>Commande validée</td>
                        <td>#ORD-7743</td>
                        <td>08/11/2025</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <footer class="mk-footer">
        <div class="mk-wrap">
            <span>© <?= date('Y') ?> Softadastra Market</span>
            <nav class="mk-footer-nav">
                <a href="/market">Accueil</a>
                <a href="/market/ping">Santé</a>
                <a href="/">Site</a>
            </nav>
        </div>
    </footer>