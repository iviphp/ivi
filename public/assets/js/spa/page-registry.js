/**
 * ivi.php — Page Registry (page-registry.js)
 *
 * Responsibilities:
 *  - Provide a registry for "page controllers"
 *  - Auto mount/unmount on SPA navigations
 *  - Idempotent lifecycle:
 *      - unmount previous page
 *      - mount new page
 *  - Provide a simple context (ctx) for pages:
 *      - container, url, page, pageId, assets, title
 *
 * Contract (HTML):
 *  Your SPA container (#app) should expose:
 *    data-page="shop.manage"
 *    data-page-id="shop.manage:v3"   (optional but recommended)
 *
 * Events (emitted by spa-core.js):
 *  - spa:dom:replaced (after #app innerHTML swap)
 *  - spa:page:init    (right after dom:replaced)
 *  - spa:page:ready
 *
 * Usage:
 *  <script defer src="/assets/js/spa/spa-core.js"></script>
 *  <script defer src="/assets/js/spa/page-registry.js"></script>
 *
 *  // Register pages (in assets/js/pages/*.js)
 *  IVI_PAGES.register("shop.manage", {
 *    mount({ container, url }) { ... },
 *    unmount() { ... }
 *  });
 */

(function (global) {
  "use strict";

  if (!global.IVI_SPA) {
    console.error(
      "[IVI_PAGES] spa-core.js is required before page-registry.js"
    );
    return;
  }

  // Registry
  const pages = new Map(); // name -> controller
  const aliases = new Map(); // alias -> name

  // Current mounted page
  let current = {
    name: null,
    pageId: null,
    controller: null,
    cleanup: [], // cleanup fns
    mountedAt: 0,
  };

  // Debug
  const log = (...a) =>
    global.IVI_SPA.config().debug && console.debug("[IVI_PAGES]", ...a);

  // Helpers
  function getContainer() {
    const sel = global.IVI_SPA.config().containerSelector || "#app";
    return document.querySelector(sel);
  }

  function readPageInfo(container) {
    if (!container) return { page: null, pageId: null };

    const page = container.getAttribute("data-page") || null;
    const pageId =
      container.getAttribute("data-page-id") ||
      container.getAttribute("data-pageid") ||
      null;

    return {
      page: page ? String(page).trim() : null,
      pageId: pageId ? String(pageId).trim() : null,
    };
  }

  function resolveName(name) {
    if (!name) return null;
    const n = String(name).trim();
    return aliases.get(n) || n;
  }

  function safeCall(fn, ...args) {
    try {
      return fn(...args);
    } catch (e) {
      console.error("[IVI_PAGES] controller error:", e);
      return undefined;
    }
  }

  async function ensureScripts(jsList) {
    if (!Array.isArray(jsList) || jsList.length === 0) return;

    for (const src of jsList) {
      if (!src) continue;

      // already loaded?
      if (document.querySelector(`script[data-ivi-src="${src}"]`)) continue;

      await new Promise((resolve, reject) => {
        const s = document.createElement("script");
        s.src = src;
        s.defer = true;
        s.dataset.iviSrc = src;
        s.onload = resolve;
        s.onerror = () => reject(new Error("Failed to load " + src));
        document.head.appendChild(s);
      });
    }
  }

  function makeCtx(detail) {
    const container = getContainer();
    const { page, pageId } = readPageInfo(container);

    return {
      // DOM
      container,

      // navigation
      url: detail?.url
        ? String(detail.url)
        : global.location.pathname + global.location.search,
      from: detail?.from ? String(detail.from) : null,

      // page identity
      page: detail?.page || page || detail?.pageId || pageId,
      pageId: detail?.pageId || pageId,

      // optional
      assets: detail?.assets || null,
      title: detail?.title || document.title || null,

      // small helpers
      qs: (sel) => (container ? container.querySelector(sel) : null),
      qsa: (sel) =>
        container ? Array.from(container.querySelectorAll(sel)) : [],
      on: (el, ev, handler, opts) => {
        if (!el || !ev || !handler) return () => {};
        el.addEventListener(ev, handler, opts);
        return () => el.removeEventListener(ev, handler, opts);
      },

      // global SPA API
      spa: global.IVI_SPA,
    };
  }

  // Core lifecycle
  function unmountCurrent() {
    if (!current.controller) return;

    log("unmount ->", current.name, current.pageId);

    // run cleanup fns added by ctx.on(...) etc.
    for (const fn of current.cleanup) {
      try {
        fn();
      } catch {}
    }
    current.cleanup = [];

    if (typeof current.controller.unmount === "function") {
      safeCall(current.controller.unmount);
    }

    current.controller = null;
    current.name = null;
    current.pageId = null;
    current.mountedAt = 0;
  }

  function mountNext(detail) {
    const ctx = makeCtx(detail);
    const rawName = ctx.page;
    const name = resolveName(rawName);

    if (!name) {
      // no page declared => just unmount previous
      unmountCurrent();
      emitLocal("pages:mounted", {
        page: null,
        pageId: ctx.pageId,
        url: ctx.url,
      });
      return;
    }

    const controller = pages.get(name);
    if (!controller) {
      // no registered controller => unmount previous but keep running
      unmountCurrent();
      log("no controller registered for page:", name);
      emitLocal("pages:missing", {
        page: name,
        pageId: ctx.pageId,
        url: ctx.url,
      });
      return;
    }

    // If same pageId and same controller, you may skip re-mount (optional)
    // BUT in SPA, DOM is replaced, so mount MUST happen again.
    // We keep it simple: always unmount then mount.
    unmountCurrent();

    current.controller = controller;
    current.name = name;
    current.pageId = ctx.pageId || null;
    current.mountedAt = Date.now();

    // Provide a way for controllers to register cleanup fns
    ctx.addCleanup = (fn) => {
      if (typeof fn === "function") current.cleanup.push(fn);
    };

    // Convenience: ctx.on(...) that auto-registers cleanup
    const originalOn = ctx.on;
    ctx.on = (el, ev, handler, opts) => {
      const off = originalOn(el, ev, handler, opts);
      if (typeof off === "function") current.cleanup.push(off);
      return off;
    };

    log("mount ->", name, ctx.pageId, ctx.url);

    if (typeof controller.mount === "function") {
      safeCall(controller.mount, ctx);
    }

    emitLocal("pages:mounted", {
      page: name,
      pageId: ctx.pageId,
      url: ctx.url,
    });
  }

  // Local events (optional, not required)
  const localListeners = new Map(); // name -> Set(fn)

  function onLocal(name, fn) {
    if (!localListeners.has(name)) localListeners.set(name, new Set());
    localListeners.get(name).add(fn);
    return () => offLocal(name, fn);
  }

  function offLocal(name, fn) {
    const set = localListeners.get(name);
    if (!set) return;
    set.delete(fn);
  }

  function emitLocal(name, detail) {
    const set = localListeners.get(name);
    if (set) {
      for (const fn of set) {
        try {
          fn(detail);
        } catch (e) {
          console.error("[IVI_PAGES] local listener error:", name, e);
        }
      }
    }
    // Also expose as DOM event for convenience
    try {
      document.dispatchEvent(new CustomEvent(`ivi:${name}`, { detail }));
    } catch {}
  }

  // Public API
  function register(name, controller) {
    const n = String(name || "").trim();
    if (!n)
      throw new Error("IVI_PAGES.register(name, controller): name required");
    if (!controller || typeof controller !== "object") {
      throw new Error(`IVI_PAGES.register(${n}): controller object required`);
    }
    pages.set(n, controller);
    log("registered:", n);
    return true;
  }

  function alias(aliasName, targetName) {
    const a = String(aliasName || "").trim();
    const t = String(targetName || "").trim();
    if (!a || !t) return false;
    aliases.set(a, t);
    return true;
  }

  function getCurrent() {
    return {
      name: current.name,
      pageId: current.pageId,
      mountedAt: current.mountedAt,
      hasController: !!current.controller,
    };
  }

  function mountNow() {
    // for initial page load (non-navigation)
    const ctx = makeCtx({ url: normalizeSelfUrl() });
    mountNext({ url: ctx.url, page: ctx.page, pageId: ctx.pageId });
  }

  function normalizeSelfUrl() {
    try {
      return location.pathname + location.search;
    } catch {
      return "/";
    }
  }

  function destroy() {
    unmountCurrent();
    // remove SPA event hook (if any)
    if (hookOff) {
      try {
        hookOff();
      } catch {}
      hookOff = null;
    }
    pages.clear();
    aliases.clear();
    localListeners.clear();
  }

  // Hook into SPA lifecycle
  // We mount on spa:page:init (DOM already replaced)
  let hookOff = global.IVI_SPA.on("spa:page:init", async (detail) => {
    try {
      const assets = detail?.assets || null;
      if (assets?.js?.length) {
        await ensureScripts(assets.js);
      }
    } catch (e) {
      console.error("[IVI_PAGES] ensureScripts failed:", e);
    }

    mountNext(detail);
  });

  // Also mount initial page once DOM is ready (important!)
  // If SPA is enabled, initial load won't trigger spa:page:init automatically.
  document.addEventListener("DOMContentLoaded", () => {
    // If SPA disabled, don't mount
    if (global.__SPA__ === false) return;
    mountNow();
  });

  // Expose
  global.IVI_PAGES = {
    register,
    alias,
    getCurrent,
    mountNow,
    unmountCurrent,
    on: onLocal,
    off: offLocal,
    destroy,
  };
})(window);
