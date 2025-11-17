/**
 * ivi.php — spa.js (advanced SPA engine)
 *
 * Features:
 *  - Prefetch on hover
 *  - Fragment caching (TTL)
 *  - Smooth transitions
 *  - History navigation (push/pop)
 *  - Smart script execution (external + inline with dedupe)
 *  - Error overlay
 *  - Auto active navigation
 */

const SPA = (function () {
  // ---------------------------------------------------------
  // CONFIG
  // ---------------------------------------------------------
  const cfg = {
    enabled: true,
    containerSelector: "#app",
    linkSelector: "a[data-spa]",
    prefetchOnHover: true,
    prefetchDebounceMs: 120,
    cacheTTL: 1000 * 60 * 5,
    transition: "fade",
    debug: false,
  };

  // ---------------------------------------------------------
  // INTERNALS
  // ---------------------------------------------------------
  const cache = new Map();
  const prefetchTimers = new Map();
  const pendingFetches = new Map();

  const loadedScripts = new Set();
  const inlineScriptHashes = new Set();

  let appContainer = null;
  let errorOverlay = null;

  const log = (...a) => cfg.debug && console.debug("[SPA]", ...a);
  const now = () => Date.now();

  // ---------------------------------------------------------
  // HELPERS
  // ---------------------------------------------------------
  const isExternal = (href) => {
    try {
      return new URL(href, location.origin).origin !== location.origin;
    } catch (e) {
      return true;
    }
  };

  const normalizeUrl = (href) => {
    try {
      const u = new URL(href, location.href);
      return u.pathname + u.search;
    } catch (e) {
      return href;
    }
  };

  // ---------------------------------------------------------
  // ERROR OVERLAY
  // ---------------------------------------------------------
  const setUpErrorOverlay = () => {
    if (errorOverlay) return;
    errorOverlay = document.createElement("div");
    errorOverlay.id = "spa-error";
    errorOverlay.style.cssText = `
      position: fixed;
      inset: 0;
      background: rgba(255,0,0,0.06);
      z-index: 99999;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      color: #900;
      padding: 1rem;
      opacity: 0;
      pointer-events: none;
      transition: opacity .22s ease;
    `;
    document.body.appendChild(errorOverlay);
  };

  const showError = (msg, autoHideMs = 3500) => {
    setUpErrorOverlay();
    errorOverlay.textContent = msg;
    errorOverlay.style.opacity = 1;
    errorOverlay.style.pointerEvents = "all";
    setTimeout(() => {
      errorOverlay.style.opacity = 0;
      errorOverlay.style.pointerEvents = "none";
    }, autoHideMs);
  };

  // ---------------------------------------------------------
  // SCRIPT HANDLING
  // ---------------------------------------------------------
  const hashString = (s) => {
    let h = 5381;
    for (let i = 0; i < s.length; i++) h = (h << 5) + h + s.charCodeAt(i);
    return (h >>> 0).toString(36);
  };

  const runPageScripts = (container) => {
    if (!container) return;

    const scripts = [...container.querySelectorAll("script")];

    for (const old of scripts) {
      try {
        if (old.src) {
          const src = old.src.split("#")[0];
          if (!loadedScripts.has(src)) {
            const s = document.createElement("script");
            s.src = src;
            s.defer = true;
            document.body.appendChild(s);
            loadedScripts.add(src);
          }
        } else {
          const txt = old.textContent || "";
          const h = hashString(txt);
          if (!inlineScriptHashes.has(h)) {
            const s = document.createElement("script");
            s.textContent = txt;
            document.body.appendChild(s);
            inlineScriptHashes.add(h);
          }
        }
      } catch (err) {
        console.error("SPA script exec error:", err);
      }
      old.remove();
    }
  };

  // ---------------------------------------------------------
  // CACHE
  // ---------------------------------------------------------
  const cacheSet = (url, html) => {
    cache.set(url, { html, ts: now() });
  };

  const cacheGet = (url) => {
    const c = cache.get(url);
    if (!c) return null;
    if (now() - c.ts > cfg.cacheTTL) {
      cache.delete(url);
      return null;
    }
    return c.html;
  };

  const clearCache = () => {
    cache.clear();
    pendingFetches.clear();
    log("cache cleared");
  };

  // ---------------------------------------------------------
  // FETCH
  // ---------------------------------------------------------
  const fetchFragment = (url) => {
    url = normalizeUrl(url);

    const cached = cacheGet(url);
    if (cached) return Promise.resolve(cached);

    if (pendingFetches.has(url)) return pendingFetches.get(url);

    const p = fetch(url, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.text();
      })
      .then((html) => {
        cacheSet(url, html);
        pendingFetches.delete(url);
        return html;
      })
      .catch((err) => {
        pendingFetches.delete(url);
        throw err;
      });

    pendingFetches.set(url, p);
    return p;
  };

  const prefetch = (url) => {
    if (!cfg.prefetchOnHover) return;
    url = normalizeUrl(url);
    if (cacheGet(url)) return;
    fetchFragment(url).catch((err) => log("prefetch failed", url, err));
  };

  // ---------------------------------------------------------
  // TRANSITIONS
  // ---------------------------------------------------------
  const applyTransitionIn = (el) => {
    if (!el) return;
    const t = cfg.transition;

    el.classList.remove(
      "spa-transition-fade-in",
      "spa-transition-slide-in",
      "spa-transition-zoom-in"
    );
    void el.offsetWidth;

    if (t === "fade") el.classList.add("spa-transition-fade-in");
    else if (t === "slide") el.classList.add("spa-transition-slide-in");
    else if (t === "zoom") el.classList.add("spa-transition-zoom-in");
  };

  // ---------------------------------------------------------
  // NAV ACTIVE LINKS
  // ---------------------------------------------------------
  const updateActiveLinks = (currentUrl = null) => {
    try {
      const u = currentUrl ? new URL(currentUrl, location.href) : location;
      const cur = (u.pathname || "/").split("?")[0];

      document.querySelectorAll(cfg.linkSelector).forEach((link) => {
        const href = link.getAttribute("href");
        if (!href) return;
        const norm = normalizeUrl(href).split("?")[0];
        const isActive = cur === norm || cur.startsWith(norm + "/");
        link.classList.toggle("active", isActive);
      });
    } catch (err) {
      console.error("SPA updateActiveLinks error:", err);
    }
  };

  // ---------------------------------------------------------
  // LINK HANDLERS
  // ---------------------------------------------------------
  const initLinkHandlers = () => {
    document.querySelectorAll(cfg.linkSelector).forEach((link) => {
      const href = link.getAttribute("href");
      if (!href || isExternal(href)) return;

      // REMOVE OLD CLICK HANDLER IF EXISTS
      if (link._spaClick) link.removeEventListener("click", link._spaClick);

      // CLICK HANDLER SPA
      link._spaClick = (e) => {
        e.preventDefault();
        const url = normalizeUrl(link.href);
        SPA.go(url);
      };
      link.addEventListener("click", link._spaClick);

      // PREFETCH ON HOVER
      if (cfg.prefetchOnHover) {
        if (link._spaHover)
          link.removeEventListener("mouseenter", link._spaHover);

        link._spaHover = () => {
          const deb = cfg.prefetchDebounceMs;
          if (prefetchTimers.has(link)) clearTimeout(prefetchTimers.get(link));

          prefetchTimers.set(
            link,
            setTimeout(() => {
              prefetch(link.href);
              prefetchTimers.delete(link);
            }, deb)
          );
        };

        link.addEventListener("mouseenter", link._spaHover);
      }
    });
  };

  // ---------------------------------------------------------
  // PAGE LOAD CORE (Safe & SPA-friendly)
  // ---------------------------------------------------------
  const loadPage = async (url, push = true) => {
    if (!appContainer) return (location.href = url);

    try {
      // Récupération HTML (cache ou fetch)
      const html = cacheGet(url) ?? (await fetchFragment(url));

      // Parser le HTML
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");

      // Cherche le fragment SPA (#app) ou fallback sur body
      const newFragment = doc.querySelector(cfg.containerSelector) || doc.body;

      // Remplace le contenu actuel
      appContainer.innerHTML = newFragment.innerHTML;

      // Execute les scripts de la page
      runPageScripts(appContainer);

      // Réinitialise les handlers des liens SPA
      initLinkHandlers();

      // Transition (fade/slide/zoom)
      applyTransitionIn(appContainer);

      // Met à jour l’historique
      if (push) {
        history.pushState(null, "", url);
        updateActiveLinks(url); // lien actif
      } else {
        updateActiveLinks(); // popstate
      }

      // Scroll en haut
      scrollTo(0, 0);

      return true;
    } catch (err) {
      console.error("SPA loadPage error:", err);

      // Message d’erreur seulement si fetch échoue
      if (err instanceof TypeError || err.message.includes("Failed to fetch")) {
        showError("Failed to fetch page. Redirecting...");
      } else {
        showError("Unexpected SPA error. Reloading...");
      }

      // Fallback full reload
      setTimeout(() => (location.href = url), 1500);
      return false;
    }
  };

  // ---------------------------------------------------------
  // INIT
  // ---------------------------------------------------------
  const init = (options = {}) => {
    Object.assign(cfg, options);

    if (window.__SPA__ === false) {
      cfg.enabled = false;
      log("SPA disabled by server");
      return;
    }

    if (!cfg.enabled) return;

    appContainer = document.querySelector(cfg.containerSelector);
    if (!appContainer) {
      log("App container not found:", cfg.containerSelector);
      return;
    }

    setUpErrorOverlay();
    initLinkHandlers();
    updateActiveLinks();

    window.addEventListener("popstate", () => {
      const url = normalizeUrl(location.href);
      loadPage(url, false);
    });

    log("SPA initialized", cfg);
  };

  // ---------------------------------------------------------
  // PUBLIC API
  // ---------------------------------------------------------
  return {
    init,
    go: (url) => loadPage(normalizeUrl(url), true),
    prefetch: (url) => prefetch(normalizeUrl(url)),
    clearCache,
    setTransition: (name) => (cfg.transition = name),
    config: () => ({ ...cfg }),
    cacheGet,
    cacheSet,
  };
})();

// AUTO-INIT
document.addEventListener("DOMContentLoaded", () => {
  SPA.init({ debug: false });
});
