/**
 * ivi.php — Actions Router (actions.js)
 *
 * Goal:
 *  - Provide a single global "action router" using event delegation.
 *  - HTML declares behavior with data-action="namespace.action"
 *  - No more per-page click bindings that disappear after SPA swaps.
 *
 * Supported patterns:
 *  - Click actions:
 *      <button data-action="cart.add" data-product-id="123">Add</button>
 *  - Optional event override:
 *      <input data-action="search.live" data-action-event="input" />
 *  - Optional preventDefault toggle:
 *      <a href="/x" data-action="nav.open" data-prevent="true">Open</a>
 *  - Optional stopPropagation toggle:
 *      data-stop="true"
 *
 * Handler signature:
 *  IVI_ACTIONS.register("cart.add", async (ctx) => { ... });
 *
 * ctx:
 *  - el: target element (the one with data-action)
 *  - event: original DOM event
 *  - action: action name
 *  - data: merged dataset + convenient accessors
 *  - page: current page info (from IVI_PAGES if available)
 *  - spa: IVI_SPA api
 *  - container: #app element
 *  - qs/qsa: scoped queries inside #app
 *
 * Notes:
 *  - This router is fully compatible with SPA swaps because it uses document-level delegation.
 *  - Pair this with forms.js to handle POST globally.
 */

(function (global) {
  "use strict";

  if (!global.IVI_SPA) {
    console.error("[IVI_ACTIONS] spa-core.js is required before actions.js");
    return;
  }

  // ---------------------------------------------------------
  // Registry
  // ---------------------------------------------------------
  const handlers = new Map(); // action -> fn
  const middlewares = []; // (ctx, next) => Promise<void>

  // Configuration
  const cfg = {
    selector: "[data-action]",
    // Allow explicit event override via data-action-event="input"
    eventAttr: "data-action-event",
    preventAttr: "data-prevent",
    stopAttr: "data-stop",

    // Default DOM event to listen for actions
    defaultEvent: "click",

    // If true: logs missing actions when debug is enabled
    logMissing: true,
  };

  const log = (...a) =>
    global.IVI_SPA.config().debug && console.debug("[IVI_ACTIONS]", ...a);

  // ---------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------
  function getContainer() {
    const sel = global.IVI_SPA.config().containerSelector || "#app";
    return document.querySelector(sel);
  }

  function getPageInfo() {
    const container = getContainer();
    const page = container?.getAttribute?.("data-page") || null;
    const pageId =
      container?.getAttribute?.("data-page-id") ||
      container?.getAttribute?.("data-pageid") ||
      null;

    let current = null;
    if (global.IVI_PAGES && typeof global.IVI_PAGES.getCurrent === "function") {
      current = global.IVI_PAGES.getCurrent();
    }

    return {
      page: (current && current.name) || (page ? String(page).trim() : null),
      pageId:
        (current && current.pageId) || (pageId ? String(pageId).trim() : null),
      container,
    };
  }

  function closestActionEl(target) {
    if (!target || !target.closest) return null;
    return target.closest(cfg.selector);
  }

  function parseAction(el) {
    const raw = el.getAttribute("data-action");
    if (!raw) return null;
    const a = String(raw).trim();
    return a || null;
  }

  function getActionEventName(el) {
    const v = el.getAttribute(cfg.eventAttr);
    return v ? String(v).trim().toLowerCase() : cfg.defaultEvent;
  }

  function wantsPrevent(el) {
    const v = el.getAttribute(cfg.preventAttr);
    if (v === null) return true; // default: prevent on click actions
    const s = String(v).trim().toLowerCase();
    return s !== "false" && s !== "0" && s !== "no";
  }

  function wantsStop(el) {
    const v = el.getAttribute(cfg.stopAttr);
    if (v === null) return false;
    const s = String(v).trim().toLowerCase();
    return s === "true" || s === "1" || s === "yes";
  }

  function buildDataProxy(el) {
    // Provides:
    //  - data.get("productId") from dataset
    //  - data.int("productId"), data.bool("enabled"), data.str("x")
    //  - data.all (raw dataset object)
    const ds = el.dataset || {};
    return {
      all: ds,
      get: (k, fallback = null) => {
        const key = String(k || "");
        if (!key) return fallback;
        return ds[key] !== undefined ? ds[key] : fallback;
      },
      str: (k, fallback = "") => {
        const v = ds[String(k || "")];
        if (v === undefined || v === null) return fallback;
        return String(v);
      },
      int: (k, fallback = 0) => {
        const v = ds[String(k || "")];
        if (v === undefined || v === null || v === "") return fallback;
        const n = parseInt(String(v), 10);
        return Number.isFinite(n) ? n : fallback;
      },
      float: (k, fallback = 0) => {
        const v = ds[String(k || "")];
        if (v === undefined || v === null || v === "") return fallback;
        const n = parseFloat(String(v));
        return Number.isFinite(n) ? n : fallback;
      },
      bool: (k, fallback = false) => {
        const v = ds[String(k || "")];
        if (v === undefined || v === null) return fallback;
        const s = String(v).trim().toLowerCase();
        if (["1", "true", "yes", "on"].includes(s)) return true;
        if (["0", "false", "no", "off"].includes(s)) return false;
        return fallback;
      },
    };
  }

  async function runChain(ctx, handler) {
    // Compose middleware stack
    let idx = -1;
    async function next() {
      idx++;
      if (idx < middlewares.length) {
        return middlewares[idx](ctx, next);
      }
      return handler(ctx);
    }
    return next();
  }

  function safeCall(fn, ctx) {
    try {
      const r = fn(ctx);
      // allow async
      if (r && typeof r.then === "function") return r;
      return Promise.resolve(r);
    } catch (e) {
      return Promise.reject(e);
    }
  }

  // ---------------------------------------------------------
  // Core dispatcher
  // ---------------------------------------------------------
  async function dispatchAction(ev, el) {
    const action = parseAction(el);
    if (!action) return;

    const handler = handlers.get(action);
    if (!handler) {
      if (cfg.logMissing && global.IVI_SPA.config().debug) {
        console.warn("[IVI_ACTIONS] missing handler for action:", action, el);
      }
      document.dispatchEvent(
        new CustomEvent("ivi:action:missing", { detail: { action } })
      );
      return;
    }

    const pageInfo = getPageInfo();

    const ctx = {
      action,
      el,
      event: ev,
      container: pageInfo.container,
      page: pageInfo.page,
      pageId: pageInfo.pageId,
      spa: global.IVI_SPA,
      // scoped queries
      qs: (sel) =>
        pageInfo.container ? pageInfo.container.querySelector(sel) : null,
      qsa: (sel) =>
        pageInfo.container
          ? Array.from(pageInfo.container.querySelectorAll(sel))
          : [],
      // dataset proxy
      data: buildDataProxy(el),
      // convenience: navigate
      go: (url, opts) => global.IVI_SPA.go(url, opts || {}),
    };

    // Emit "start"
    document.dispatchEvent(
      new CustomEvent("ivi:action:start", {
        detail: { action, page: ctx.page },
      })
    );

    try {
      await runChain(ctx, (c) => safeCall(handler, c));
      document.dispatchEvent(
        new CustomEvent("ivi:action:done", {
          detail: { action, page: ctx.page },
        })
      );
    } catch (e) {
      console.error("[IVI_ACTIONS] handler error:", action, e);
      document.dispatchEvent(
        new CustomEvent("ivi:action:error", {
          detail: {
            action,
            page: ctx.page,
            error: String(e && e.message ? e.message : e),
          },
        })
      );
    }
  }

  // ---------------------------------------------------------
  // Event delegation
  // ---------------------------------------------------------
  function handleEvent(ev) {
    const target = ev.target;
    const el = closestActionEl(target);
    if (!el) return;

    // If element specifies a different event, ignore here (it will be caught by its own listener)
    const desired = getActionEventName(el);
    if (desired !== ev.type) return;

    if (wantsStop(el)) ev.stopPropagation();
    if (wantsPrevent(el)) ev.preventDefault();

    dispatchAction(ev, el);
  }

  // Some actions want input/change/keydown etc.
  // Instead of attaching listeners per element (which SPA swaps break),
  // we attach a few common events at document-level.
  const delegatedEvents = new Set([
    "click",
    "input",
    "change",
    "submit",
    "keydown",
    "keyup",
    "focusin",
  ]);

  function attachDelegates() {
    for (const evName of delegatedEvents) {
      document.addEventListener(evName, handleEvent, true);
    }
  }

  function detachDelegates() {
    for (const evName of delegatedEvents) {
      document.removeEventListener(evName, handleEvent, true);
    }
  }

  // ---------------------------------------------------------
  // Public API
  // ---------------------------------------------------------
  function register(actionName, fn) {
    const name = String(actionName || "").trim();
    if (!name)
      throw new Error(
        "IVI_ACTIONS.register(actionName, fn): actionName required"
      );
    if (typeof fn !== "function")
      throw new Error(`IVI_ACTIONS.register(${name}): fn must be a function`);
    handlers.set(name, fn);
    log("registered:", name);
    return true;
  }

  function unregister(actionName) {
    const name = String(actionName || "").trim();
    if (!name) return false;
    return handlers.delete(name);
  }

  function has(actionName) {
    return handlers.has(String(actionName || "").trim());
  }

  function list() {
    return Array.from(handlers.keys()).sort();
  }

  function use(mw) {
    if (typeof mw !== "function")
      throw new Error("IVI_ACTIONS.use(mw): mw must be a function");
    middlewares.push(mw);
    return true;
  }

  function config(opts = {}) {
    Object.assign(cfg, opts);
    return { ...cfg };
  }

  // ---------------------------------------------------------
  // Init
  // ---------------------------------------------------------
  let inited = false;
  function init() {
    if (inited) return true;
    if (global.__SPA__ === false) return false;

    attachDelegates();
    inited = true;
    log("initialized");
    return true;
  }

  function destroy() {
    detachDelegates();
    handlers.clear();
    middlewares.length = 0;
    inited = false;
  }

  // Auto-init
  document.addEventListener("DOMContentLoaded", () => {
    init();
  });

  global.IVI_ACTIONS = {
    init,
    destroy,
    register,
    unregister,
    has,
    list,
    use,
    config,
  };
})(window);
