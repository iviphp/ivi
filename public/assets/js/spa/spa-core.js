/**
 * ivi.php — SPA Core (spa-core.js)
 *
 * Responsibilities:
 *  - Navigation (click interception + history push/pop)
 *  - Fragment fetching (AJAX) with dedupe + cache (TTL)
 *  - Lifecycle events (before-navigate / dom:replaced / page:init / page:ready)
 *  - Minimal DOM swap into a container (#app by default)
 *  - Title handling via server header (X-Page-Title) or document.title fallback
 *  - Optional prefetch on hover
 *
 * Not included here (handled by other modules):
 *  - Page registry mount/unmount (page-registry.js)
 *  - Actions router (actions.js)
 *  - Form interceptor (forms.js)
 *
 * Server contract (recommended):
 *  - AJAX request header: X-Requested-With: XMLHttpRequest
 *  - Response headers:
 *      X-Page-Title: string
 *      X-Page-Id: string (optional but recommended)
 *      X-Page-Assets: JSON string {"css":[...],"js":[...]} (optional)
 *
 * Usage:
 *  <script defer src="/assets/js/spa/spa-core.js"></script>
 *  <script defer>
 *    document.addEventListener("DOMContentLoaded", () => {
 *      IVI_SPA.init({ containerSelector: "#app", linkSelector: "a[data-spa]" });
 *    });
 *  </script>
 */

(function (global) {
  "use strict";

  // Defaults
  const DEFAULTS = {
    enabled: true,

    // Container to swap
    containerSelector: "#app",

    // Links to intercept
    // Best practice: mark only your SPA links with data-spa
    linkSelector: "a[data-spa]",

    // Prefetch
    prefetchOnHover: true,
    prefetchDebounceMs: 120,

    // Cache
    cacheTTL: 1000 * 60 * 5, // 5min
    cacheMaxEntries: 50,

    // Request headers
    ajaxHeaderName: "X-Requested-With",
    ajaxHeaderValue: "XMLHttpRequest",

    // Fetch options
    credentials: "same-origin",

    // Debug
    debug: false,

    // If true, will try to parse a full HTML page and extract #app
    // If false, expects server to return fragment HTML directly for AJAX calls
    parseFullDocumentFallback: true,

    // Title
    defaultTitle: "Softadastra",
    useHeaderTitle: true,
    headerTitleName: "X-Page-Title",

    // Optional headers for page meta
    headerPageIdName: "X-Page-Id",
    headerAssetsName: "X-Page-Assets",

    // Scroll behavior
    scrollToTopOnNavigate: true,
  };

  // Internals
  let cfg = { ...DEFAULTS };
  let container = null;

  const cache = new Map(); // url -> { html, headers, ts }
  const pendingFetches = new Map(); // url -> Promise
  const prefetchTimers = new Map(); // element -> timer

  const listeners = new Map(); // eventName -> Set(handler)

  const log = (...args) => cfg.debug && console.debug("[IVI_SPA]", ...args);

  // Utils
  function now() {
    return Date.now();
  }

  function normalizeUrl(href) {
    try {
      const u = new URL(href, location.href);
      // keep path + search only (no hash) for navigation identity
      return u.pathname + u.search;
    } catch (e) {
      return href;
    }
  }

  function isExternalLink(a) {
    try {
      const u = new URL(a.href, location.href);
      return u.origin !== location.origin;
    } catch (e) {
      return true;
    }
  }

  async function ensureScripts(jsList) {
    if (!Array.isArray(jsList) || jsList.length === 0) return;

    for (const src of jsList) {
      if (!src) continue;

      // déjà chargé ?
      if (document.querySelector(`script[data-ivi-src="${src}"]`)) continue;

      await new Promise((resolve, reject) => {
        const s = document.createElement("script");
        s.src = src;
        s.defer = true;
        s.dataset.iviSrc = src;
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
      });
    }
  }

  function isModifiedClick(ev) {
    return (
      ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || ev.button !== 0
    );
  }

  function safeJsonParse(s) {
    try {
      return JSON.parse(s);
    } catch {
      return null;
    }
  }

  function clampCacheSize() {
    if (cache.size <= cfg.cacheMaxEntries) return;
    // Remove oldest entries
    const entries = Array.from(cache.entries()).sort(
      (a, b) => a[1].ts - b[1].ts
    );
    const toRemove = entries.slice(
      0,
      Math.max(0, cache.size - cfg.cacheMaxEntries)
    );
    for (const [k] of toRemove) cache.delete(k);
  }

  // Event system (internal + DOM CustomEvent)
  function on(eventName, handler) {
    if (!listeners.has(eventName)) listeners.set(eventName, new Set());
    listeners.get(eventName).add(handler);
    return () => off(eventName, handler);
  }

  function off(eventName, handler) {
    const set = listeners.get(eventName);
    if (!set) return;
    set.delete(handler);
  }

  function emit(eventName, detail) {
    // internal listeners
    const set = listeners.get(eventName);
    if (set) {
      for (const fn of set) {
        try {
          fn(detail);
        } catch (e) {
          console.error("[IVI_SPA] listener error", eventName, e);
        }
      }
    }

    // DOM event
    try {
      document.dispatchEvent(new CustomEvent(eventName, { detail }));
    } catch (e) {
      // ignore
    }
  }

  // Cache
  function cacheGet(url) {
    const key = normalizeUrl(url);
    const entry = cache.get(key);
    if (!entry) return null;
    if (now() - entry.ts > cfg.cacheTTL) {
      cache.delete(key);
      return null;
    }
    return entry;
  }

  function cacheSet(url, html, headersObj) {
    const key = normalizeUrl(url);
    cache.set(key, { html, headers: headersObj || {}, ts: now() });
    clampCacheSize();
  }

  function clearCache() {
    cache.clear();
    pendingFetches.clear();
    log("cache cleared");
  }

  // Fetch fragment (dedupe + cache)
  async function fetchFragment(url, { allowCache = true } = {}) {
    const key = normalizeUrl(url);

    if (allowCache) {
      const cached = cacheGet(key);
      if (cached) return cached;
    }

    if (pendingFetches.has(key)) return pendingFetches.get(key);

    const p = (async () => {
      const res = await fetch(key, {
        method: "GET",
        headers: {
          [cfg.ajaxHeaderName]: cfg.ajaxHeaderValue,
        },
        credentials: cfg.credentials,
      });

      if (!res.ok) {
        const text = await res.text().catch(() => "");
        const err = new Error(`Fetch failed (${res.status})`);
        err.status = res.status;
        err.body = text;
        throw err;
      }

      const html = await res.text();

      // Collect important headers
      const headersObj = {
        title: cfg.useHeaderTitle
          ? res.headers.get(cfg.headerTitleName) || null
          : null,
        pageId: res.headers.get(cfg.headerPageIdName) || null,
        assets:
          safeJsonParse(res.headers.get(cfg.headerAssetsName) || "") || null,
      };

      const entry = { html, headers: headersObj, ts: now() };
      cacheSet(key, html, headersObj);
      return entry;
    })().finally(() => pendingFetches.delete(key));

    pendingFetches.set(key, p);
    return p;
  }

  // DOM extraction
  function parseIncoming(html) {
    // Best case: server returns fragment only => treat as fragment
    // Fallback: parse full document and extract containerSelector
    if (!cfg.parseFullDocumentFallback) {
      return { fragmentHtml: html, titleFromDoc: null, pageAttr: null };
    }

    // Try parse as full doc (safe even for fragment; it will be a doc with body)
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, "text/html");

    // If doc contains <html> / <head> etc, treat as full; else fragment still works.
    const fragNode = doc.querySelector(cfg.containerSelector) || doc.body;

    // Read optional data-page/data-page-id from incoming fragment
    let pageAttr = null;
    try {
      pageAttr = {
        page: fragNode?.getAttribute?.("data-page") || null,
        pageId: fragNode?.getAttribute?.("data-page-id") || null,
      };
    } catch {}

    const titleFromDoc =
      doc && doc.title && doc.title.trim() ? doc.title.trim() : null;
    const fragmentHtml = fragNode ? fragNode.innerHTML : html;

    return { fragmentHtml, titleFromDoc, pageAttr };
  }

  // Title
  function setTitle(title) {
    const t = String(title || "").trim();
    if (!t) return;
    document.title = t;
  }

  // Navigation / Swap
  async function go(url, { pushState = true, allowCache = true } = {}) {
    if (!cfg.enabled) {
      location.href = url;
      return false;
    }
    if (!container) {
      container = document.querySelector(cfg.containerSelector);
      if (!container) {
        location.href = url;
        return false;
      }
    }

    const to = normalizeUrl(url);
    const from = normalizeUrl(location.href);

    emit("spa:before-navigate", { from, to });

    let entry;
    try {
      entry = await fetchFragment(to, { allowCache });
    } catch (e) {
      console.error("[IVI_SPA] fetch error:", e);
      // hard fallback
      location.href = to;
      return false;
    }

    const { html, headers } = entry;
    const parsed = parseIncoming(html);

    // Decide title:
    // 1) Header X-Page-Title
    // 2) <title> in doc (if full doc)
    // 3) data-title/data-spa-title on container after swap
    // 4) defaultTitle
    const headerTitle = headers?.title ? String(headers.title).trim() : null;
    if (headerTitle) setTitle(headerTitle);
    else if (parsed.titleFromDoc) setTitle(parsed.titleFromDoc);

    // Perform swap
    // NOTE: do NOT run page scripts here; other modules handle init/mount.
    container.innerHTML = parsed.fragmentHtml;

    // Load page assets (JS) before telling registry to mount
    const assets = headers?.assets || null;
    if (assets?.js?.length) {
      try {
        await ensureScripts(assets.js);
      } catch (e) {
        console.error("[IVI_SPA] asset load failed", e);
      }
    }

    // After swap, try to pick title from container attr
    try {
      const t2 =
        container.getAttribute("data-spa-title") ||
        container.getAttribute("data-title") ||
        container
          .querySelector?.("[data-title]")
          ?.getAttribute?.("data-title") ||
        null;

      if (!headerTitle && t2 && String(t2).trim()) setTitle(String(t2).trim());
      if (!document.title) setTitle(cfg.defaultTitle);
    } catch {
      if (!document.title) setTitle(cfg.defaultTitle);
    }

    // History
    if (pushState) {
      history.pushState({ spa: true, url: to }, "", to);
    }

    // Lifecycle events (the rest of the SPA system hooks here)
    const pageId = headers?.pageId || parsed?.pageAttr?.pageId || null;

    // IMPORTANT: fallback => if no data-page in fragment, use X-Page-Id
    const pageName = parsed?.pageAttr?.page || pageId || null;

    // Keep container attributes in sync for other modules (actions/forms/pages)
    try {
      if (pageName) container.setAttribute("data-page", pageName);
      if (pageId) container.setAttribute("data-page-id", pageId);
    } catch {}

    emit("spa:dom:replaced", {
      url: to,
      from,
      pageId,
      page: pageName,
      assets: headers?.assets || null,
      title: headerTitle || parsed.titleFromDoc || null,
    });

    emit("spa:page:init", {
      url: to,
      pageId,
      page: pageName,
      assets: headers?.assets || null,
    });

    try {
      const pid = pageId || pageName;
      if (pid && globalThis.IVI_PAGES?.mount) {
        globalThis.IVI_PAGES.mount(pid, {
          spa: API,
          qs: (sel, root) => (root || document).querySelector(sel),
          qsa: (sel, root) =>
            Array.from((root || document).querySelectorAll(sel)),
          addCleanup: (fn) => {},
        });
      }
    } catch (e) {
      console.error("[IVI_SPA] mount failed", e);
    }

    emit("spa:page:ready", {
      url: to,
      pageId,
      page: pageName,
    });

    if (cfg.scrollToTopOnNavigate) {
      try {
        scrollTo(0, 0);
      } catch {}
    }

    return true;
  }

  function handleClick(ev) {
    if (!cfg.enabled) return;

    if (isModifiedClick(ev)) return;

    const a =
      ev.target && ev.target.closest
        ? ev.target.closest(cfg.linkSelector)
        : null;
    if (!a) return;

    // allow opt-out
    if (a.hasAttribute("data-spa-off")) return;

    // ignore external
    if (isExternalLink(a)) return;

    const href = a.getAttribute("href");
    if (!href || href.startsWith("#")) return;

    ev.preventDefault();
    go(a.href, { pushState: true, allowCache: true });
  }

  function handleHover(ev) {
    if (!cfg.enabled || !cfg.prefetchOnHover) return;

    const a =
      ev.target && ev.target.closest
        ? ev.target.closest(cfg.linkSelector)
        : null;
    if (!a) return;
    if (a.hasAttribute("data-spa-off")) return;
    if (isExternalLink(a)) return;

    const href = a.getAttribute("href");
    if (!href || href.startsWith("#")) return;

    const deb = cfg.prefetchDebounceMs;
    if (prefetchTimers.has(a)) clearTimeout(prefetchTimers.get(a));

    prefetchTimers.set(
      a,
      setTimeout(() => {
        prefetchTimers.delete(a);
        prefetch(a.href);
      }, deb)
    );
  }

  async function prefetch(url) {
    const key = normalizeUrl(url);
    if (cacheGet(key)) return true;
    try {
      await fetchFragment(key, { allowCache: true });
      log("prefetch ok:", key);
      emit("spa:prefetch:done", { url: key });
      return true;
    } catch (e) {
      log("prefetch failed:", key, e);
      emit("spa:prefetch:fail", {
        url: key,
        error: String(e && e.message ? e.message : e),
      });
      return false;
    }
  }

  // Popstate
  function handlePopState() {
    if (!cfg.enabled) return;
    const url = normalizeUrl(location.href);
    go(url, { pushState: false, allowCache: true });
  }

  // Init / Destroy
  function init(options = {}) {
    cfg = { ...cfg, ...options };

    // server can disable SPA globally
    if (global.__SPA__ === false) cfg.enabled = false;
    if (!cfg.enabled) {
      log("SPA disabled");
      return false;
    }

    container = document.querySelector(cfg.containerSelector);
    if (!container) {
      console.warn("[IVI_SPA] container not found:", cfg.containerSelector);
      return false;
    }

    // Ensure initial title
    if (!document.title) setTitle(cfg.defaultTitle);

    // Listeners (delegated)
    document.addEventListener("click", handleClick, true);
    document.addEventListener("mouseenter", handleHover, true);

    window.addEventListener("popstate", handlePopState);

    emit("spa:initialized", {
      containerSelector: cfg.containerSelector,
      linkSelector: cfg.linkSelector,
      cacheTTL: cfg.cacheTTL,
    });

    log("initialized", cfg);
    return true;
  }

  function destroy() {
    document.removeEventListener("click", handleClick, true);
    document.removeEventListener("mouseenter", handleHover, true);
    window.removeEventListener("popstate", handlePopState);
    clearCache();
    container = null;
    emit("spa:destroyed", {});
  }

  // Public API
  const API = {
    init,
    destroy,

    go: (url, opts) => go(url, opts || {}),
    prefetch: (url) => prefetch(url),

    // cache
    cacheGet: (url) => cacheGet(url),
    clearCache,

    // events
    on,
    off,
    emit,

    // config
    config: () => ({ ...cfg }),

    // title
    setTitle,
  };

  global.IVI_SPA = API;
})(window);
