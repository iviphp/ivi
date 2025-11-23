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

  // ---- Ajout pour styles ----
  const loadedStyles = new Set(); // href absolu des link.css déjà ajoutés
  const inlineStyleHashes = new Set(); // hash des <style> déjà injectés

  let appContainer = null;
  let errorOverlay = null;

  /**
   * Maximum wait time (in milliseconds) for external stylesheets to load.
   * If a stylesheet takes longer than this, it is marked as failed but the SPA
   * continues smoothly without blocking navigation.
   */

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

  // =======================
  // fetchFragment
  // =======================
  const fetchFragment = async (url) => {
    try {
      const response = await fetch(url, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
      });

      if (!response.ok) {
        throw new Error(`Fetch failed with status ${response.status}`);
      }

      const html = await response.text();
      // Lire le header X-Page-Title envoyé par le serveur
      const headerTitle = response.headers.get("X-Page-Title") || null;

      return { html, headerTitle };
    } catch (e) {
      console.error("[SPA] fetchFragment error:", e);
      throw e;
    }
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

  // timeout max pour charger les CSS (ms)

  /**
   * Loads all CSS from a fetched page into the current SPA view — smoothly, safely,
   * and without freezing the UI.
   *
   * This function is the "styling teleporter" of the SPA engine:
   *  - Scans the incoming Document for <link rel="stylesheet"> and <style> tags.
   *  - Injects missing CSS into <head> without duplicating anything already loaded.
   *  - Tracks external stylesheet loading using Promises, with a safety timeout to
   *    avoid getting stuck on slow or broken links.
   *  - Clones inline <style> blocks instantly (using content hashing to skip repeats).
   *
   * Because of this, page transitions feel native: no flashes, no layout jumps,
   * and no full reload. Just seamless style syncing.
   *
   * @param {Document} doc - The HTML document whose styles should be imported.
   * @returns {Promise<Array>} Resolves when all external stylesheets finish loading
   *                           (or time out), giving detailed results for debugging.
   */

  const STYLE_LOAD_TIMEOUT_MS = 5000; // 5000ms = 5 secondes, tu peux ajuster

  // ---------------------------
  // Nouvelle option de nettoyage
  // ---------------------------
  Object.assign(cfg, {
    // strategy: "keep-shared" | "remove-all" | "strict"
    // - keep-shared : supprime seulement les styles marqués page-scoped absents dans la nouvelle page
    //                 mais laisse toutes les feuilles "non marquées" (safe default).
    // - remove-all   : supprime tout ce que SPA a injecté auparavant (data-spa) qui n'est pas présent maintenant.
    // - strict       : comme remove-all mais retire aussi les styles non-marques si ils semblent être non-shared
    spaPageCleanupStrategy: "keep-shared",
    // liste whitelist persistante (href or hash) à conserver toujours (utile pour libs globales)
    spaPersistentStyleWhitelist: new Set(),
  });

  // helper heuristique : est-ce que ce style est "partagé" globalement (heuristique)
  const isSharedStyle = (hrefOrHash) => {
    // heuristique simple :
    // - si l'URL contient '/assets/' ou '/vendor/' ou 'bootstrap' => probablement partagée
    // - si presente dans whitelist cfg.spaPersistentStyleWhitelist
    if (!hrefOrHash) return false;
    const s = hrefOrHash.toLowerCase();
    if (cfg.spaPersistentStyleWhitelist.has(hrefOrHash)) return true;
    if (
      s.includes("/assets/") ||
      s.includes("/vendor/") ||
      s.includes("bootstrap") ||
      s.includes("tailwind") ||
      s.includes("bulma") ||
      s.includes("foundation")
    )
      return true;
    return false;
  };

  // ---------------------------
  // preserveFormState(container, newFragment)
  // copie les valeurs/selection des <input>, <textarea>, <select> et les data-* utiles
  // pour éviter la perte d'espaces et des valeurs utilisateur lors du innerHTML replace
  // ---------------------------
  const preserveFormState = (container, newFragment) => {
    try {
      if (!container || !newFragment) return;
      // map key -> value for inputs (use name + type + position fallback)
      const oldInputs = container.querySelectorAll("input, textarea, select");
      for (const old of oldInputs) {
        try {
          // find a matching element in newFragment by id OR name + type OR by index
          let selector = old.id ? `#${CSS.escape(old.id)}` : null;
          let newEl = selector ? newFragment.querySelector(selector) : null;

          if (!newEl && old.name) {
            newEl = newFragment.querySelector(`[name="${old.name}"]`);
          }

          // if not found, try same tag sequence fallback (index)
          if (!newEl) {
            const tag = old.tagName.toLowerCase();
            const idx = Array.from(container.querySelectorAll(tag)).indexOf(
              old
            );
            const cand = Array.from(newFragment.querySelectorAll(tag))[idx];
            if (cand) newEl = cand;
          }

          if (!newEl) continue;

          if (old.tagName.toLowerCase() === "select") {
            try {
              newEl.value = old.value;
              // for multiple selects, copy selectedOptions
              if (old.multiple && old.selectedOptions) {
                for (const opt of newEl.options) opt.selected = false;
                for (const opt of old.selectedOptions) {
                  const match = Array.from(newEl.options).find(
                    (o) => o.value === opt.value || o.text === opt.text
                  );
                  if (match) match.selected = true;
                }
              }
            } catch (e) {}
          } else if (old.tagName.toLowerCase() === "textarea") {
            newEl.value = old.value;
          } else {
            // input
            const type = (old.type || "").toLowerCase();
            if (type === "checkbox" || type === "radio") {
              newEl.checked = old.checked;
            } else {
              // preserve exact value (including spaces)
              newEl.value = old.value;
            }
          }

          // preserve selection/caret for text inputs
          if (
            typeof old.selectionStart === "number" &&
            typeof old.selectionEnd === "number"
          ) {
            try {
              newEl.selectionStart = old.selectionStart;
              newEl.selectionEnd = old.selectionEnd;
            } catch (e) {}
          }

          // preserve dataset attributes that might impact layout
          for (const key of Object.keys(old.dataset || {})) {
            try {
              newEl.dataset[key] = old.dataset[key];
            } catch (e) {}
          }
        } catch (e) {
          if (cfg.debug)
            console.debug("preserveFormState: element copy failed", e);
        }
      }
    } catch (err) {
      if (cfg.debug) console.debug("preserveFormState error", err);
    }
  };

  // ---------------------------
  // runHeadScripts améliorée (déjà présent mais légèrement raffiné)
  // - respecte type/module
  // - dedupe via loadedScripts / inlineScriptHashes
  // - returns nothing
  // ---------------------------
  const runHeadScripts = (doc) => {
    if (!cfg.execHeadScripts) return;
    try {
      const scripts = [
        ...(doc.head ? doc.head.querySelectorAll("script") : []),
      ];
      for (const old of scripts) {
        try {
          const type = old.type || "text/javascript";
          if (old.src) {
            const src = old.src.split("#")[0];
            if (!loadedScripts.has(src)) {
              const s = document.createElement("script");
              s.src = src;
              s.type = type;
              if (old.async) s.async = true;
              if (old.defer) s.defer = true;
              // if module, keep as module
              if (type === "module") s.type = "module";
              s.addEventListener("load", () => loadedScripts.add(src));
              s.addEventListener("error", () => {
                loadedScripts.add(src);
                if (cfg.debug)
                  console.error("[SPA] head script load error:", src);
              });
              // append to body to avoid blocking head parsing in SPA
              document.body.appendChild(s);
            }
          } else {
            const txt = old.textContent || "";
            const h = hashString(txt);
            if (!inlineScriptHashes.has(h)) {
              const s = document.createElement("script");
              if (type) s.type = type;
              s.textContent = txt;
              document.body.appendChild(s);
              inlineScriptHashes.add(h);
            }
          }
        } catch (err) {
          console.error("SPA head script exec error:", err);
        }
      }
    } catch (err) {
      console.debug("runHeadScripts error:", err);
    }
  };

  // ---------------------------
  // runPageStylesAsync améliorée + nettoyage selon cfg.spaPageCleanupStrategy
  // - marque data-spa-page si l'élément distant le demande
  // - construit addedPageKeys (hrefs/hashes) pour la nouvelle page
  // - applique cleanup selon strategy: "keep-shared" | "remove-all" | "strict"
  // ---------------------------
  const runPageStylesAsync = (doc) => {
    if (!doc) return Promise.resolve();

    const linkNodes = [
      ...doc.querySelectorAll(
        'link[rel="stylesheet"], link[rel="preload"][as="style"]'
      ),
    ];
    const styleNodes = [...doc.querySelectorAll("style")];

    const loadPromises = [];
    const addedPageKeys = new Set(); // hrefs / hashes marqués page-scoped ajoutés maintenant

    for (const l of linkNodes) {
      try {
        const hrefAttr = l.getAttribute("href") || l.href;
        if (!hrefAttr) continue;
        const abs = new URL(hrefAttr, location.href).href.split("#")[0];

        if (loadedStyles.has(abs)) {
          if (l.hasAttribute("data-spa-page")) addedPageKeys.add(abs);
          continue;
        }

        const nl = document.createElement("link");
        const rel = l.getAttribute("rel") || "stylesheet";
        nl.rel = rel === "preload" ? "stylesheet" : rel;
        nl.href = abs;

        if (l.hasAttribute("data-spa-page")) {
          nl.setAttribute("data-spa-page", "1");
          addedPageKeys.add(abs);
        }

        nl.dataset.spa = "true";

        const p = new Promise((resolve) => {
          let done = false;
          const clean = () => {
            nl.removeEventListener("load", onload);
            nl.removeEventListener("error", onerror);
            done = true;
          };
          const onload = () => {
            clean();
            loadedStyles.add(abs);
            resolve({ href: abs, ok: true });
          };
          const onerror = () => {
            clean();
            loadedStyles.add(abs);
            resolve({ href: abs, ok: false });
          };

          nl.addEventListener("load", onload);
          nl.addEventListener("error", onerror);

          setTimeout(() => {
            if (!done) {
              clean();
              loadedStyles.add(abs);
              resolve({ href: abs, ok: false, timeout: true });
            }
          }, cfg.styleLoadTimeoutMs || STYLE_LOAD_TIMEOUT_MS);
        });

        document.head.appendChild(nl);
        loadPromises.push(p);
      } catch (err) {
        console.debug("SPA style link skip:", err);
      }
    }

    for (const s of styleNodes) {
      try {
        const txt = s.textContent || "";
        const h = hashString(txt);
        if (!inlineStyleHashes.has(h)) {
          const ns = document.createElement("style");
          ns.textContent = txt;
          if (s.hasAttribute("data-spa-page")) {
            ns.setAttribute("data-spa-page", "1");
            addedPageKeys.add(h);
          }
          ns.dataset.spa = "true";
          document.head.appendChild(ns);
          inlineStyleHashes.add(h);
        } else {
          if (s.hasAttribute("data-spa-page")) addedPageKeys.add(h);
        }
      } catch (err) {
        console.debug("SPA inline style skip:", err);
      }
    }

    // CLEANUP selon strategy
    try {
      if (cfg.cleanPageScopedStyles) {
        const existing = [
          ...document.head.querySelectorAll(
            '[data-spa-page], [data-spa="true"]'
          ),
        ];
        for (const node of existing) {
          try {
            let key = null;
            const tag = node.tagName.toLowerCase();
            if (tag === "link")
              key = node.href ? node.href.split("#")[0] : null;
            else if (tag === "style") key = hashString(node.textContent || "");

            // ne pas tenter de supprimer les whitelisted persistent
            if (cfg.spaPersistentStyleWhitelist.has(key)) continue;

            const inAdded = key && addedPageKeys.has(key);

            if (cfg.spaPageCleanupStrategy === "keep-shared") {
              // remove only those that were explicitly page-scoped and not present now
              if (node.hasAttribute("data-spa-page") && !inAdded) {
                node.remove();
              }
              // keep everything else (shared or not-marked)
            } else if (cfg.spaPageCleanupStrategy === "remove-all") {
              // remove any SPA-injected node that is not present in current page
              if (!inAdded) {
                node.remove();
              }
            } else if (cfg.spaPageCleanupStrategy === "strict") {
              // stricter: remove node if not in addedPageKeys and not obviously shared
              if (!inAdded) {
                if (key && !isSharedStyle(key)) {
                  node.remove();
                } else if (!key) {
                  // conservative: if no key, remove (it's likely an inline page style)
                  node.remove();
                }
              }
            } else {
              // fallback safe = keep-shared behavior
              if (node.hasAttribute("data-spa-page") && !inAdded) node.remove();
            }
          } catch (e) {
            if (cfg.debug) console.debug("cleanup node failed", e);
          }
        }
      }
    } catch (err) {
      if (cfg.debug) console.debug("cleanPageScopedStyles error", err);
    }

    if (loadPromises.length === 0) return Promise.resolve();
    return Promise.all(loadPromises).then((results) => {
      const failed = results.filter((r) => !r.ok);
      if (failed.length && cfg.debug)
        console.debug("[SPA] some styles failed or timed out:", failed);
      return results;
    });
  };

  // ---------------------------
  // runPageScripts améliorée (respect type/module, dedupe, onerror)
  // ---------------------------
  const runPageScripts = (container) => {
    if (!container) return;
    const scripts = [...container.querySelectorAll("script")];
    for (const old of scripts) {
      try {
        const type = old.type || "text/javascript";
        if (old.src) {
          const src = old.src.split("#")[0];
          if (!loadedScripts.has(src)) {
            const s = document.createElement("script");
            s.src = src;
            s.type = type;
            if (old.async) s.async = true;
            if (old.defer) s.defer = true;
            if (type === "module") s.type = "module";
            s.addEventListener("load", () => loadedScripts.add(src));
            s.addEventListener("error", () => {
              loadedScripts.add(src);
              if (cfg.debug) console.error("[SPA] script load error:", src);
            });
            document.body.appendChild(s);
          }
        } else {
          const txt = old.textContent || "";
          const h = hashString(txt);
          if (!inlineScriptHashes.has(h)) {
            const s = document.createElement("script");
            s.type = type;
            s.textContent = txt;
            document.body.appendChild(s);
            inlineScriptHashes.add(h);
          }
        }
      } catch (err) {
        console.error("SPA script exec error:", err);
      }
      try {
        old.remove();
      } catch (e) {}
    }
  };

  // ---------------------------
  // utilitaire debug : log computed margins d'un selecteur
  // ---------------------------
  const logComputedMargins = (selector, tag = "") => {
    const el = document.querySelector(selector);
    if (!el) {
      console.warn(
        `[SPA] logComputedMargins: element not found ${selector}`,
        tag
      );
      return;
    }
    const cs = getComputedStyle(el);
    console.log(`[SPA] computed margins ${selector} ${tag}:`, {
      marginTop: cs.marginTop,
      marginRight: cs.marginRight,
      marginBottom: cs.marginBottom,
      marginLeft: cs.marginLeft,
    });
  };

  const syncHtmlAndBodyAttrs = (doc) => {
    if (!doc) return;
    // Copier les classes et attributs de <html>
    document.documentElement.className = doc.documentElement.className;
    for (const attr of doc.documentElement.attributes) {
      if (attr.name !== "class")
        document.documentElement.setAttribute(attr.name, attr.value);
    }

    // Copier les classes et attributs de <body>
    document.body.className = doc.body.className;
    for (const attr of doc.body.attributes) {
      if (attr.name !== "class")
        document.body.setAttribute(attr.name, attr.value);
    }
  };

  window.__runHljs = async function () {
    // Attendre que hljs soit chargé
    if (!window.hljs) {
      await new Promise((resolve) => {
        const check = () => {
          if (window.hljs) resolve();
          else setTimeout(check, 50);
        };
        check();
      });
    }

    // Configurer Highlight.js
    hljs.configure({
      languages: [
        "php",
        "javascript",
        "json",
        "bash",
        "html",
        "css",
        "ini",
        "yaml",
      ],
    });

    // Highlight tout le code
    document.querySelectorAll(".markdown-body pre code").forEach((el) => {
      hljs.highlightElement(el);
    });
  };

  // --- Title helper (INTERNALS) ---
  let lastSpaTitle = null;
  const setSpaTitle = (t) => {
    if (!t) return;
    try {
      const s = String(t).trim();
      if (!s) return;
      lastSpaTitle = s;
      document._spaTitleRequest = s; // compat
      document.title = s;
      if (cfg.debug) console.log("[SPA] setSpaTitle ->", s);
    } catch (e) {
      if (cfg.debug) console.debug("[SPA] setSpaTitle failed", e);
    }
  };

  // --- begin patch: copy meta + signal page init after fragment injection ---
  /**
   * Copy important <meta> tags (csrf, etc.) from fetched doc into current head
   * so server-side checks that rely on meta (csrf-token) keep working.
   */
  const copyHeadMeta = (doc) => {
    try {
      if (!doc || !doc.head) return;
      const metaNames = ["csrf-token", "csrf-token-name", "csrf-param"]; // ajoute ce dont ton backend a besoin
      for (const name of metaNames) {
        const m = doc.head.querySelector(`meta[name="${name}"]`);
        if (m) {
          // replace or add in current head
          let exist = document.head.querySelector(`meta[name="${name}"]`);
          if (exist) {
            exist.setAttribute("content", m.getAttribute("content") || "");
          } else {
            const nm = document.createElement("meta");
            nm.name = name;
            nm.content = m.getAttribute("content") || "";
            document.head.appendChild(nm);
          }
        }
      }
    } catch (e) {
      if (cfg.debug) console.debug("copyHeadMeta failed", e);
    }
  };

  /**
   * Dispatch a custom event to let page scripts re-bind handlers.
   * Also attempt to call common global init if present.
   */
  const notifyFragmentLoaded = (url) => {
    try {
      document.dispatchEvent(
        new CustomEvent("spa:fragment:loaded", {
          detail: { url: String(url) },
        })
      );

      // If page defines a global init function, call it.
      if (window.pageInit && typeof window.pageInit === "function") {
        try {
          window.pageInit();
        } catch (e) {
          if (cfg.debug) console.debug("pageInit failed", e);
        }
      }

      // compatibility: older pages might listen for 'render' event
      document.dispatchEvent(
        new CustomEvent("spa:render", { detail: { url: String(url) } })
      );
    } catch (e) {
      if (cfg.debug) console.debug("notifyFragmentLoaded failed", e);
    }
  };

  function updateCsrfToken() {
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    if (!tokenMeta) return;

    const token = tokenMeta.getAttribute("content");
    if (!token) return;

    // mettre à jour tous les champs CSRF dans les formulaires SPA
    document.querySelectorAll('input[name="csrf_token"]').forEach((input) => {
      input.value = token;
    });
  }

  // -----------------------------------------------------
  // Auto-init d'une page selon l'URL, ex : /user/home → pageUserHome.init()
  function callPageInitFromUrl(url) {
    const clean = url.replace(/\?.*$/, "").replace(/^\/+|\/+$/g, "");
    if (!clean) return;

    const parts = clean.split("/");
    const name =
      "page" +
      parts.map((x) => x.charAt(0).toUpperCase() + x.slice(1)).join("");

    let attempts = 0;
    const tryInit = () => {
      if (window[name]?.init) {
        window[name].init();
        console.log("[SPA] Auto init →", name);
      } else if (attempts++ < 20) {
        setTimeout(tryInit, 50); // retry toutes les 50ms max 1s
      } else {
        console.warn(`[SPA] Page init not found for ${name}`);
      }
    };

    tryInit();
  }

  // -----------------------------------------------------
  // loadPage final
  // -----------------------------------------------------
  const loadPage = async (url, push = true) => {
    if (!appContainer) return (location.href = url);

    try {
      // 1) récupérer HTML + headerTitle
      let html,
        headerTitle = null;

      const cached = cacheGet(url);
      if (cached) {
        html = cached;
      } else {
        const res = await fetchFragment(url); // { html, headerTitle }
        html = res.html;
        headerTitle = res.headerTitle;
      }

      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");

      // 2) synchroniser <html> + <body>
      syncHtmlAndBodyAttrs(doc);

      // 3) exécuter scripts head si activé
      if (cfg.execHeadScripts) runHeadScripts(doc);

      // 4) Helper titre
      const pickTitleFromDoc = (d) => {
        try {
          const docTitle = d.title?.trim() || null;
          const og =
            d
              .querySelector('meta[property="og:title"]')
              ?.getAttribute("content") || null;
          const metaName =
            d.querySelector('meta[name="title"]')?.getAttribute("content") ||
            null;

          return { docTitle, og, metaName };
        } catch (e) {
          return { docTitle: null, og: null, metaName: null };
        }
      };

      // 5) Première passe titre
      if (headerTitle?.trim()) {
        setSpaTitle(headerTitle.trim());
      } else {
        const cand = pickTitleFromDoc(doc);
        if (cand.docTitle) setSpaTitle(cand.docTitle);
        else if (cand.og) setSpaTitle(cand.og);
        else if (cand.metaName) setSpaTitle(cand.metaName);
      }

      // 6) sélectionner fragment
      const newFragment = doc.querySelector(cfg.containerSelector) || doc.body;

      // 7) masquer pour éviter flash
      const prevVisibility = appContainer.style.visibility;
      const prevOpacity = appContainer.style.opacity;
      appContainer.style.visibility = "hidden";
      appContainer.style.opacity = "0";

      // 8) appliquer styles async
      await runPageStylesAsync(doc);

      // 9) préserver états form + injection
      preserveFormState(appContainer, newFragment);
      appContainer.innerHTML = newFragment.innerHTML;

      // mise à jour CSRF
      updateCsrfToken();

      // 10) mise à jour data-spa-title
      try {
        if (headerTitle?.trim()) {
          appContainer.setAttribute("data-spa-title", headerTitle.trim());
        } else {
          const fragTitleAttr =
            newFragment.getAttribute("data-spa-title") ||
            newFragment.getAttribute("data-title") ||
            newFragment
              .querySelector("[data-title]")
              ?.getAttribute("data-title") ||
            null;

          if (fragTitleAttr?.trim()) {
            setSpaTitle(fragTitleAttr.trim());
            appContainer.setAttribute("data-spa-title", fragTitleAttr.trim());
          }
        }
      } catch (e) {
        if (cfg.debug) console.debug("[SPA] fragment title update failed", e);
      }

      // 11) exécuter scripts fragment
      runPageScripts(appContainer);

      document.dispatchEvent(
        new CustomEvent("spa:page-ready", { detail: { url } })
      );

      // -----------------------------------------------------
      // 11.1) AUTO INIT DE LA PAGE ACTUELLE
      // -----------------------------------------------------
      callPageInitFromUrl(url);

      // copier meta (CSRF...)
      copyHeadMeta(doc);

      // dispatch event
      notifyFragmentLoaded(url);

      // 12) syntax highlighting
      if (window.__runHljs) window.__runHljs();

      // 13) ré-init des handlers
      initLinkHandlers();

      // 14) transition
      setTimeout(() => {
        appContainer.style.visibility = prevVisibility || "";
        appContainer.style.opacity = prevOpacity || "";
        applyTransitionIn(appContainer);
      }, 10);

      // 15) history + active links
      if (push) {
        history.pushState(null, "", url);
        updateActiveLinks(url);
      } else {
        updateActiveLinks();
      }

      // 16) scroll top
      scrollTo(0, 0);

      // 17) second pass titre
      setTimeout(() => {
        try {
          const fragAttrNow =
            appContainer.getAttribute("data-spa-title") ||
            appContainer.getAttribute("data-title") ||
            appContainer
              .querySelector("[data-title]")
              ?.getAttribute("data-title") ||
            null;

          if (fragAttrNow?.trim()) {
            setSpaTitle(fragAttrNow.trim());
          } else {
            const cand2 = pickTitleFromDoc(doc);
            if (cand2.docTitle) setSpaTitle(cand2.docTitle);
            else if (cand2.og) setSpaTitle(cand2.og);
            else if (cand2.metaName) setSpaTitle(cand2.metaName);
          }
        } catch (e) {
          if (cfg.debug) console.debug("[SPA] second-pass title error", e);
        }
      }, cfg.titleUpdateDelayMs ?? 60);

      return true;
    } catch (err) {
      console.error("SPA loadPage error:", err);
      showError("SPA page load failed. Reloading...");
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

    // -------------------------------
    // titre initial
    // -------------------------------
    try {
      let initialTitle = null;

      if (
        document._spaTitleRequest &&
        typeof document._spaTitleRequest === "string"
      ) {
        initialTitle = document._spaTitleRequest;
        delete document._spaTitleRequest;
      } else {
        initialTitle =
          appContainer.getAttribute("data-title") ||
          appContainer.getAttribute("data-spa-title") ||
          null;
      }

      if (initialTitle && initialTitle.trim()) {
        setSpaTitle(initialTitle.trim());
        appContainer.setAttribute("data-spa-title", initialTitle.trim());
      } else {
        // fallback sûr
        setSpaTitle("Softadastra");
        appContainer.setAttribute("data-spa-title", "Softadastra");
      }
    } catch (e) {
      if (cfg.debug) console.debug("[SPA] initial title setup failed", e);
    }

    setUpErrorOverlay();
    initLinkHandlers();
    updateActiveLinks();

    document.addEventListener("spa:requested-setTitle", (ev) => {
      try {
        const t = ev?.detail?.title;
        if (t) {
          setSpaTitle(t);
          appContainer.setAttribute("data-spa-title", String(t));
        }
      } catch (e) {
        if (cfg.debug) console.debug("[SPA] event setTitle handler failed", e);
      }
    });

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
    setTitle: (t) => setSpaTitle(t),
    config: () => ({ ...cfg }),
    cacheGet,
    cacheSet,
  };
})();

// AUTO-INIT
document.addEventListener("DOMContentLoaded", () => {
  SPA.init({ debug: false });
});
