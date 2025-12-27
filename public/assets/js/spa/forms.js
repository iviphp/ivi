/**
 * ivi.php — Forms Interceptor (forms.js)
 *
 * Goal:
 *  - Handle POST/PUT/PATCH/DELETE submits globally via event delegation.
 *  - Eliminate per-page "AJAX submit" functions that break after SPA swaps.
 *
 * Pattern:
 *  <form data-spa-form action="/api/x" method="post">
 *    ...
 *  </form>
 *
 * Optional attributes:
 *  - data-spa-form="true"            (marker; presence is enough)
 *  - data-spa-method="post"          (override method)
 *  - data-spa-reset="true"           (reset form on success)
 *  - data-spa-disable="true"         (disable submit buttons while pending)
 *  - data-spa-confirm="Are you sure?" (confirm prompt)
 *
 * Response strategies:
 *  1) If server responds JSON: we emit events and optionally show message.
 *  2) If server responds HTML and:
 *     - header X-SPA-Redirect is present => navigate to it
 *     - header X-SPA-Reload = "true"     => reload current SPA page (GET)
 *     - or data-spa-navigate="/path"     => navigate after success
 *
 * Notes:
 *  - CSRF: reads <meta name="csrf-token"> and sends:
 *      - header: X-CSRF-Token
 *      - and also injects into FormData field "csrf_token" if present
 *    You can adjust to your backend.
 *
 * Events (DOM CustomEvent):
 *  - ivi:form:before   { form, url, method }
 *  - ivi:form:success  { form, url, method, status, data, text, headers }
 *  - ivi:form:error    { form, url, method, status, error, text, headers }
 *  - ivi:form:finally  { form, url, method }
 *
 * Dependencies:
 *  - spa-core.js (IVI_SPA)
 *  - actions.js optional (not required)
 */

(function (global) {
  "use strict";

  if (!global.IVI_SPA) {
    console.error("[IVI_FORMS] spa-core.js is required before forms.js");
    return;
  }

  // ---------------------------------------------------------
  // Config
  // ---------------------------------------------------------
  const cfg = {
    selector: "form[data-spa-form]",

    // If true, will intercept forms inside #app only
    scopeToContainer: false,

    // CSRF
    csrfMetaName: "csrf-token",
    csrfHeaderName: "X-CSRF-Token",
    csrfFieldName: "csrf_token", // if your backend expects a form field

    // JSON hint header
    acceptJson: true,

    // UI feedback
    debug: false,
    defaultTimeoutMs: 20000,

    // Behavior
    followRedirects: true, // fetch follows redirects by default; we also respect X-SPA-Redirect
  };

  const log = (...a) =>
    (cfg.debug || global.IVI_SPA.config().debug) &&
    console.debug("[IVI_FORMS]", ...a);

  // ---------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------
  function getContainer() {
    const sel = global.IVI_SPA.config().containerSelector || "#app";
    return document.querySelector(sel);
  }

  function inScope(form) {
    if (!cfg.scopeToContainer) return true;
    const c = getContainer();
    return !!(c && form && c.contains(form));
  }

  function getCsrfToken() {
    const meta = document.querySelector(`meta[name="${cfg.csrfMetaName}"]`);
    return meta ? String(meta.getAttribute("content") || "").trim() : "";
  }

  function wants(v, def = false) {
    if (v === null || v === undefined) return def;
    const s = String(v).trim().toLowerCase();
    if (["1", "true", "yes", "on"].includes(s)) return true;
    if (["0", "false", "no", "off"].includes(s)) return false;
    return def;
  }

  function methodFromForm(form) {
    const override = form.getAttribute("data-spa-method");
    const m = override || form.getAttribute("method") || form.method || "POST";
    return String(m).trim().toUpperCase() || "POST";
  }

  function urlFromForm(form) {
    const a = form.getAttribute("action") || form.action || location.pathname;
    // keep same-origin
    try {
      const u = new URL(a, location.href);
      return u.pathname + u.search;
    } catch {
      return a;
    }
  }

  function disableForm(form, disabled) {
    try {
      const btns = form.querySelectorAll(
        'button, input[type="submit"], input[type="button"]'
      );
      btns.forEach((b) => {
        if (disabled) {
          b.dataset._spaDisabled = b.disabled ? "1" : "0";
          b.disabled = true;
        } else {
          const was = b.dataset._spaDisabled;
          delete b.dataset._spaDisabled;
          b.disabled = was === "1";
        }
      });
    } catch {}
  }

  function emit(name, detail) {
    try {
      document.dispatchEvent(new CustomEvent(name, { detail }));
    } catch {}
  }

  function parseHeaders(res) {
    const out = {};
    try {
      res.headers.forEach((v, k) => (out[k.toLowerCase()] = v));
    } catch {}
    return out;
  }

  function isJsonResponse(res) {
    const ct = (res.headers.get("content-type") || "").toLowerCase();
    return ct.includes("application/json") || ct.includes("+json");
  }

  async function readResponseBody(res) {
    // Try JSON then text
    if (isJsonResponse(res)) {
      try {
        return { kind: "json", data: await res.json() };
      } catch {
        // fallback to text
      }
    }
    try {
      return { kind: "text", data: await res.text() };
    } catch {
      return { kind: "text", data: "" };
    }
  }

  function buildFormData(form) {
    const fd = new FormData(form);

    // Ensure CSRF exists (if backend needs it as field)
    const csrf = getCsrfToken();
    if (csrf && cfg.csrfFieldName) {
      // only set if field exists OR if you want to enforce always
      if (form.querySelector(`input[name="${cfg.csrfFieldName}"]`)) {
        fd.set(cfg.csrfFieldName, csrf);
      }
    }

    return fd;
  }

  function withTimeout(ms, signal) {
    const controller = new AbortController();
    const t = setTimeout(() => controller.abort(new Error("timeout")), ms);
    // If caller gives a signal, bridge it
    if (signal) {
      try {
        signal.addEventListener("abort", () =>
          controller.abort(signal.reason || new Error("aborted"))
        );
      } catch {}
    }
    return { controller, clear: () => clearTimeout(t) };
  }

  // ---------------------------------------------------------
  // Submission core
  // ---------------------------------------------------------
  async function submitForm(form, ev) {
    if (!form || form.nodeName !== "FORM") return;

    if (!inScope(form)) return;

    const confirmMsg = form.getAttribute("data-spa-confirm");
    if (confirmMsg && !window.confirm(confirmMsg)) return;

    const method = methodFromForm(form);
    const url = urlFromForm(form);

    const shouldReset = wants(form.getAttribute("data-spa-reset"), false);
    const shouldDisable = wants(form.getAttribute("data-spa-disable"), true);

    emit("ivi:form:before", { form, url, method });

    if (shouldDisable) disableForm(form, true);

    // Abort / timeout
    const { controller, clear } = withTimeout(cfg.defaultTimeoutMs);

    // Build request
    const headers = {
      [global.IVI_SPA.config().ajaxHeaderName || "X-Requested-With"]:
        global.IVI_SPA.config().ajaxHeaderValue || "XMLHttpRequest",
    };

    // CSRF header
    const csrf = getCsrfToken();
    if (csrf) headers[cfg.csrfHeaderName] = csrf;

    if (cfg.acceptJson) {
      headers["Accept"] = "application/json, text/html;q=0.9, */*;q=0.8";
    }

    // Body rules:
    // - For GET: no body (but we normally don't intercept GET forms)
    // - For POST/PUT/PATCH/DELETE: send FormData
    let body = null;
    if (method !== "GET" && method !== "HEAD") {
      body = buildFormData(form);
    }

    // Some backends only accept POST + _method
    // If you want, you can implement: if method != POST => set fd._method and use POST.
    const useMethodOverride = wants(
      form.getAttribute("data-spa-method-override"),
      false
    );
    let actualMethod = method;
    if (useMethodOverride && method !== "POST" && body instanceof FormData) {
      body.set("_method", method);
      actualMethod = "POST";
    }

    let res;
    let parsed;
    try {
      res = await fetch(url, {
        method: actualMethod,
        headers,
        body,
        credentials: global.IVI_SPA.config().credentials || "same-origin",
        signal: controller.signal,
        redirect: cfg.followRedirects ? "follow" : "manual",
      });

      const headersObj = parseHeaders(res);
      parsed = await readResponseBody(res);

      if (!res.ok) {
        // error path
        emit("ivi:form:error", {
          form,
          url,
          method,
          status: res.status,
          error: parsed.kind === "json" ? parsed.data : parsed.data,
          text: parsed.kind === "text" ? parsed.data : null,
          data: parsed.kind === "json" ? parsed.data : null,
          headers: headersObj,
        });
        return;
      }

      // success path
      emit("ivi:form:success", {
        form,
        url,
        method,
        status: res.status,
        data: parsed.kind === "json" ? parsed.data : null,
        text: parsed.kind === "text" ? parsed.data : null,
        headers: headersObj,
      });

      if (shouldReset) {
        try {
          form.reset();
        } catch {}
      }

      // Post-success navigation rules:
      // 1) X-SPA-Redirect header
      const redirectTo = res.headers.get("X-SPA-Redirect");
      if (redirectTo) {
        await global.IVI_SPA.go(redirectTo, {
          pushState: true,
          allowCache: false,
        });
        return;
      }

      // 2) data-spa-navigate="/path"
      const nav = form.getAttribute("data-spa-navigate");
      if (nav) {
        await global.IVI_SPA.go(nav, { pushState: true, allowCache: false });
        return;
      }

      // 3) X-SPA-Reload true => reload current page fragment
      const reload = res.headers.get("X-SPA-Reload");
      if (reload && wants(reload, false)) {
        await global.IVI_SPA.go(location.pathname + location.search, {
          pushState: false,
          allowCache: false,
        });
        return;
      }

      // 4) If response is HTML and form declares swap target: data-spa-swap="#selector"
      //    then swap the returned HTML into that target (useful for partial renders)
      const swapSel = form.getAttribute("data-spa-swap");
      if (swapSel && parsed.kind === "text") {
        const target = document.querySelector(swapSel);
        if (target) {
          target.innerHTML = parsed.data;
          emit("ivi:form:swapped", {
            form,
            url,
            method,
            target,
            selector: swapSel,
          });
        }
      }
    } catch (e) {
      const msg = String(e && e.message ? e.message : e);
      emit("ivi:form:error", {
        form,
        url,
        method,
        status: 0,
        error: msg,
        text: null,
        data: null,
        headers: null,
      });
      console.error("[IVI_FORMS] submit error:", e);
    } finally {
      clear();
      if (shouldDisable) disableForm(form, false);
      emit("ivi:form:finally", { form, url, method });
    }
  }

  // ---------------------------------------------------------
  // Delegated listener
  // ---------------------------------------------------------
  function onSubmit(ev) {
    const form =
      ev.target && ev.target.closest ? ev.target.closest(cfg.selector) : null;
    if (!form) return;

    // Only intercept real form submit, not if user wants native
    if (form.hasAttribute("data-spa-off")) return;

    // For GET forms, we can route to SPA navigation instead of fetch submit
    const method = methodFromForm(form);
    if (method === "GET") {
      ev.preventDefault();
      const url = urlFromForm(form);
      // Append query params from the form
      try {
        const fd = new FormData(form);
        const u = new URL(url, location.origin);
        for (const [k, v] of fd.entries()) {
          if (typeof v === "string") u.searchParams.set(k, v);
        }
        global.IVI_SPA.go(u.pathname + "?" + u.searchParams.toString(), {
          pushState: true,
          allowCache: true,
        });
      } catch {
        global.IVI_SPA.go(url, { pushState: true, allowCache: true });
      }
      return;
    }

    ev.preventDefault();
    submitForm(form, ev);
  }

  // Optional: support buttons that trigger form submit via data-spa-submit
  function onClick(ev) {
    const btn =
      ev.target && ev.target.closest
        ? ev.target.closest("[data-spa-submit]")
        : null;
    if (!btn) return;
    const sel = btn.getAttribute("data-spa-submit");
    if (!sel) return;
    const form = document.querySelector(sel);
    if (form && form.matches(cfg.selector)) {
      ev.preventDefault();
      submitForm(form, ev);
    }
  }

  // ---------------------------------------------------------
  // Public API
  // ---------------------------------------------------------
  let inited = false;

  function init(options = {}) {
    if (inited) return true;
    if (global.__SPA__ === false) return false;

    Object.assign(cfg, options);

    document.addEventListener("submit", onSubmit, true);
    document.addEventListener("click", onClick, true);

    inited = true;
    log("initialized", cfg);
    return true;
  }

  function destroy() {
    document.removeEventListener("submit", onSubmit, true);
    document.removeEventListener("click", onClick, true);
    inited = false;
  }

  // Auto-init
  document.addEventListener("DOMContentLoaded", () => {
    init();
  });

  global.IVI_FORMS = {
    init,
    destroy,
    config: (o) => {
      Object.assign(cfg, o || {});
      return { ...cfg };
    },
    submit: (form) => submitForm(form, null),
  };
})(window);
