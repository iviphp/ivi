// ======= Auth helpers réutilisés (mêmes clés que ton module) =======
const AUTH_MODE = window.SA_AUTH_MODE || "token"; // "cookie" | "token"
const TOKEN_KEY = window.SA_TOKEN_KEY || "sa_token"; // ex: "shop_token"

function getToken() {
  try {
    return localStorage.getItem(TOKEN_KEY) || null;
  } catch {
    return null;
  }
}
function setToken(t) {
  try {
    t ? localStorage.setItem(TOKEN_KEY, t) : localStorage.removeItem(TOKEN_KEY);
  } catch {}
}
function jwtIsExpired(token) {
  try {
    const [_, payload] = token.split(".");
    const p = JSON.parse(atob(payload.replace(/-/g, "+").replace(/_/g, "/")));
    if (typeof p.exp !== "number") return true;
    return Math.floor(Date.now() / 1000) >= p.exp;
  } catch {
    return true;
  }
}
function buildAuthHeaders() {
  if (AUTH_MODE === "cookie") return {};
  const t = getToken();
  if (!t || jwtIsExpired(t)) return {};
  return { Authorization: `Bearer ${t}` };
}

/* =========================================================
   Softadastra — Login + Flash via showMessage / closePopup
   Dépendances :
   - jQuery
   - fonctions showMessage(type, options) et closePopup()
   ========================================================= */

// Bloc d'initialisation du formulaire de login (version SPA-friendly)
// ======= Softadastra — Login SPA + Flash =======
(function () {
  "use strict";

  const AUTH_MODE = window.SA_AUTH_MODE || "token"; // "cookie" | "token"
  const TOKEN_KEY = window.SA_TOKEN_KEY || "sa_token"; // ex: "shop_token"

  function getToken() {
    try {
      return localStorage.getItem(TOKEN_KEY) || null;
    } catch {
      return null;
    }
  }

  function setToken(t) {
    try {
      t
        ? localStorage.setItem(TOKEN_KEY, t)
        : localStorage.removeItem(TOKEN_KEY);
    } catch {}
  }

  function jwtIsExpired(token) {
    try {
      const [_, payload] = token.split(".");
      const p = JSON.parse(atob(payload.replace(/-/g, "+").replace(/_/g, "/")));
      return (
        typeof p.exp !== "number" || Math.floor(Date.now() / 1000) >= p.exp
      );
    } catch {
      return true;
    }
  }

  // ======= SA Auth Fetch consolidé =======
  async function saAuthFetch(url, opts = {}) {
    opts = opts || {};
    opts.headers = opts.headers || {};

    // CSRF depuis meta ou input
    const csrfMeta = document
      .querySelector('meta[name="csrf-token"]')
      ?.getAttribute("content");
    const csrfInput = document.querySelector('input[name="csrf_token"]')?.value;
    if (csrfInput) opts.headers["X-CSRF-TOKEN"] = csrfInput;
    else if (csrfMeta) opts.headers["X-CSRF-TOKEN"] = csrfMeta;

    // Token Authorization si mode "token"
    if (AUTH_MODE === "token") {
      const t = getToken();
      if (t && !jwtIsExpired(t)) opts.headers["Authorization"] = `Bearer ${t}`;
    }

    // cookies
    if (!opts.credentials) opts.credentials = "include";

    return fetch(url, opts);
  }

  // ======= Initialisation login form =======
  async function initLoginForm() {
    try {
      const $loginForm = $("#loginForm");
      if (!$loginForm.length) return;

      // inject CSRF hidden si absent
      if ($loginForm.find('input[name="csrf_token"]').length === 0) {
        const meta = document
          .querySelector('meta[name="csrf-token"]')
          ?.getAttribute("content");
        if (meta)
          $loginForm.append(
            `<input type="hidden" name="csrf_token" value="${meta}">`
          );
      }

      const $submitBtn = $("#custom-login-login");

      function setSubmitting(isSubmitting) {
        if (isSubmitting) {
          $submitBtn
            .addClass("is-loading")
            .attr({ disabled: true, "aria-busy": "true" });
          showMessage("loading", {
            text: "Signing you in…",
            module: "Auth",
            autoCloseMs: 0,
          });
        } else {
          $submitBtn
            .removeClass("is-loading")
            .attr({ disabled: false, "aria-busy": "false" });
        }
      }

      setSubmitting(false);

      // Detach old handlers pour éviter doublons
      $loginForm.off("keydown", "input");
      $loginForm.off("submit");

      let failedAttempts =
        parseInt(localStorage.getItem("loginFailedAttempts") || "0", 10) || 0;
      const MAX_ATTEMPTS = 5;
      const BLOCK_DURATION = 10 * 60 * 1000; // 10 minutes
      const lastFailedTime =
        parseInt(localStorage.getItem("lastFailedTime") || "0", 10) || 0;

      if (
        failedAttempts >= MAX_ATTEMPTS &&
        Date.now() - lastFailedTime < BLOCK_DURATION
      ) {
        const remainingMinutes = Math.ceil(
          (BLOCK_DURATION - (Date.now() - lastFailedTime)) / 60000
        );
        showBlockedStatus(remainingMinutes);
        return;
      }
      updateButtonAppearance();

      let submitting = false;

      // Entrée déclenche submit
      $loginForm.on("keydown", "input", function (event) {
        if (event.key === "Enter") {
          event.preventDefault();
          $submitBtn.click();
        }
      });

      // Submit SPA
      $loginForm.on("submit", async function (event) {
        event.preventDefault();
        if (submitting) return;
        submitting = true;
        setSubmitting(true);

        // Next param
        let rawNext = $("#nextParam").val() || "/";
        try {
          const u = new URL(rawNext, location.origin);
          rawNext = u.origin === location.origin ? u.pathname + u.search : "/";
        } catch {
          rawNext = "/";
        }
        const nextRelative = rawNext;

        // Debug form
        console.debug("[SPA][auth] form body:", $(this).serialize());

        try {
          const resp = await saAuthFetch("/auth/login", {
            method: "POST",
            headers: {
              "Content-Type":
                "application/x-www-form-urlencoded; charset=UTF-8",
            },
            body: $(this).serialize(),
          });

          let data = {};
          try {
            data = await resp.json();
          } catch {}

          if (resp.ok && data.success) {
            if (data.token) setToken(data.token);
            closePopup();
            showMessage("success", {
              text: data.message || "Welcome!",
              module: "Auth",
              autoCloseMs: 1200,
            });
            localStorage.removeItem("loginFailedAttempts");
            localStorage.removeItem("lastFailedTime");
            localStorage.removeItem("flash_closed");

            // Redirection
            let redirect =
              data.redirect || nextRelative || document.referrer || "/";
            if (!redirect.includes("#")) redirect += "#__sa_after_login";
            setTimeout(() => location.assign(redirect), 700);
            return;
          }

          // Gestion erreurs backend
          if (data.blocked) {
            failedAttempts = MAX_ATTEMPTS;
            localStorage.setItem("loginFailedAttempts", String(failedAttempts));
            localStorage.setItem("lastFailedTime", String(Date.now()));
            showBlockedStatus(data.remaining || 10);
          } else showErrorMessage(data);
        } catch (err) {
          closePopup();
          showErrorMessage({ message: "Network error." });
        } finally {
          submitting = false;
          setSubmitting(false);
          updateButtonAppearance();
        }
      });

      // ---- helpers ----
      function updateButtonAppearance() {
        const $btn = $submitBtn;
        if (failedAttempts >= 3) {
          $btn.css({
            "background-color": "#dc3545",
            "border-color": "#dc3545",
            color: "#fff",
          });
        } else {
          $btn.css({ "background-color": "", "border-color": "", color: "" });
        }
      }

      function showBlockedStatus(minutes) {
        $submitBtn.prop("disabled", true).text(`Blocked (${minutes}m)`).css({
          "background-color": "#dc3545",
          "border-color": "#dc3545",
          color: "#fff",
        });
        showMessage("error", {
          text: `Too many attempts. Try again in ${minutes} minute(s).`,
          module: "Auth",
          autoCloseMs: 0,
        });
      }

      function showErrorMessage(data) {
        let text = "An unexpected error occurred.";
        if (!data) text = "No response from server.";
        else if (typeof data === "string") text = data;
        else if (data.errors)
          text = Object.values(data.errors).flat().join(" • ");
        else if (data.message) text = data.message;
        showMessage("error", { text, module: "Auth", autoCloseMs: 0 });
      }
      // ---- fin helpers ----
    } catch (err) {
      console.debug("initLoginForm error", err);
    }
  }

  // Expose global pour SPA et appel manuel
  window.pageInit = initLoginForm;

  // DOMContentLoaded
  document.addEventListener("DOMContentLoaded", () => {
    initLoginForm().catch(() => {});
  });

  // Après injection SPA
  document.addEventListener("spa:fragment:loaded", () => {
    initLoginForm().catch(() => {});
  });

  // Appel immédiat si jQuery déjà prêt
  if (window.jQuery && document.readyState === "complete")
    initLoginForm().catch(() => {});
})();

// ======= 4) Divers =======
function goBack() {
  window.location.href = "/";
}

const registerBtn = document.getElementById("custom-register");
if (registerBtn) {
  registerBtn.addEventListener("click", () => {
    window.location.href = "/register";
  });
}
// Utilitaires d’ouverture/fermeture non-intrusifs
(function () {
  const AUTOHIDE_MS = {
    success: 3000,
    error: 5000,
    toast: 2500,
  };
  const closeBtns = document.querySelectorAll(".sa-popup__close");

  function setA11y(el, { role = "status", live = "polite" } = {}) {
    if (!el) return;
    el.setAttribute("role", role);
    el.setAttribute("aria-live", live);
    el.setAttribute("aria-modal", "false");
  }

  // Init ARIA
  setA11y(document.getElementById("popupMessage"), {
    role: "status",
    live: "polite",
  });
  setA11y(document.getElementById("success-popup"), {
    role: "status",
    live: "polite",
  });
  setA11y(document.getElementById("error-popup"), {
    role: "alert",
    live: "assertive",
  });

  // Close buttons
  closeBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      const box = btn.closest(".sa-popup, .sa-flash");
      if (!box) return;
      if (box.id === "popupMessage") box.style.display = "none";
      else box.classList.remove("show");
    });
  });

  // ESC pour fermer
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    const openFlash = document.querySelector(".sa-flash.show");
    if (openFlash) openFlash.classList.remove("show");
    const toast = document.getElementById("popupMessage");
    if (toast && toast.style.display !== "none") toast.style.display = "none";
  });

  // Helpers globaux (facultatifs) – sûrs si existants
  window.SAFlash = {
    toast(msg) {
      const box = document.getElementById("popupMessage");
      if (!box) return;
      box.querySelector("#popupText").innerHTML = msg || "";
      box.style.display = "block";
      clearTimeout(box.__t);
      box.__t = setTimeout(
        () => (box.style.display = "none"),
        AUTOHIDE_MS.toast
      );
    },
    success(msg) {
      const box = document.getElementById("success-popup");
      if (!box) return;
      const p = box.querySelector("#success-message");
      if (p) p.textContent = msg || "Success";
      box.classList.add("show");
      clearTimeout(box.__t);
      box.__t = setTimeout(
        () => box.classList.remove("show"),
        AUTOHIDE_MS.success
      );
    },
    error(content) {
      const box = document.getElementById("error-popup");
      if (!box) return;
      const ul = box.querySelector("#error-message");
      if (ul) {
        if (typeof content === "string") ul.innerHTML = `<li>${content}</li>`;
        else if (content && typeof content === "object") {
          ul.innerHTML = Object.values(content)
            .map((v) => `<li>${v}</li>`)
            .join("");
        } else ul.innerHTML = `<li>Unexpected error</li>`;
      }
      box.classList.add("show");
      clearTimeout(box.__t);
      box.__t = setTimeout(
        () => box.classList.remove("show"),
        AUTOHIDE_MS.error
      );
    },
  };
})();
