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
// fetch avec cookies + Authorization valide (si dispo)
async function saAuthFetch(url, opts = {}) {
  const res = await fetch(url, {
    credentials: "include",
    ...opts,
    headers: { ...(opts.headers || {}), ...buildAuthHeaders() },
  });
  return res;
}

// ======= 1) Google OAuth (client) =======
async function handleGoogleLoginClick(event) {
  event.preventDefault();

  // next sûr et relatif à ton origine
  const rawNext = location.href;
  const nextParam = encodeURIComponent(rawNext);

  // Sauvegarde client (fallback post-OAuth)
  try {
    sessionStorage.setItem(
      "sa_post_login",
      JSON.stringify({
        next: rawNext,
        scrollY: window.scrollY || 0,
        ts: Date.now(),
        action: "login",
      })
    );
  } catch {}

  // Récupère l’URL OAuth côté serveur (avec cookies + token si valide)
  try {
    const r = await saAuthFetch("/google-login-url");
    const data = await r.json().catch(() => ({}));

    if (data && data.url) {
      const finalUrl =
        data.url +
        (data.url.includes("?") ? "&" : "?") +
        "next=" +
        nextParam +
        "&no_pwa_redirect=1";

      const isPWA = window.matchMedia("(display-mode: standalone)").matches;
      if (isPWA) window.open(finalUrl, "_blank");
      else window.location.href = finalUrl;
    } else {
      alert("Oops! We couldn’t get the Google sign-in link.");
    }
  } catch {
    alert("Oops! There was a problem communicating with the server.");
  }
}
/* =========================================================
   Softadastra — Login + Flash via showMessage / closePopup
   Dépendances :
   - jQuery
   - fonctions showMessage(type, options) et closePopup()
   ========================================================= */

$(async function () {
  /* ---------- 1) Flash messages init (via API) ---------- */
  try {
    const resp = await saAuthFetch("/api/get-flash");
    const json = await resp.json().catch(() => ({}));
    const messages =
      json && json.messages ? json.messages : { success: [], error: [] };

    (messages.success || []).forEach((m) => {
      showMessage("success", {
        text: typeof m === "string" ? m : String(m),
        module: "Flash",
        autoCloseMs: 3000,
        closeOnBackdrop: true,
        closeOnSwipe: true,
      });
    });

    (messages.error || []).forEach((err) => {
      const text =
        typeof err === "string"
          ? err
          : err && typeof err === "object"
          ? Object.values(err).join(" • ")
          : "Unexpected error";
      showMessage("error", {
        text,
        module: "Flash",
        autoCloseMs: 0,
        closeOnBackdrop: true,
        closeOnSwipe: true,
      });
    });
  } catch {
    // silencieux: flash facultatif
  }

  /* ---------- 2) Login form (tentatives, blocage, redirect) ---------- */
  const $submitBtn = $("#custom-login-login");

  function setSubmitting(isSubmitting) {
    if (isSubmitting) {
      $submitBtn
        .addClass("is-loading")
        .attr({ disabled: true, "aria-busy": "true" });
      // Modal loading
      showMessage("loading", {
        text: "Signing you in…",
        module: "Auth",
        autoCloseMs: 0,
        closeOnBackdrop: false,
        closeOnSwipe: false,
        lockScroll: false,
        showBackdrop: false,
      });
    } else {
      $submitBtn
        .removeClass("is-loading")
        .attr({ disabled: false, "aria-busy": "false" });
      // On ne ferme pas forcément ici : chaque branche gère closePopup()
    }
  }

  // état par défaut
  setSubmitting(false);

  let failedAttempts =
    parseInt(localStorage.getItem("loginFailedAttempts") || "0", 10) || 0;
  const MAX_ATTEMPTS = 5;
  const BLOCK_DURATION = 10 * 60 * 1000; // 10 min

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

  // garde-fou double-submit
  let submitting = false;

  $("#loginForm").on("submit", async function (event) {
    event.preventDefault();
    if (submitting) return;
    submitting = true;
    setSubmitting(true);

    const rawNext =
      new URLSearchParams(location.search).get("next") ||
      document.referrer ||
      "/";
    const u = new URL(rawNext, location.origin);
    const nextRelative =
      u.origin === location.origin ? u.pathname + u.search : "/";

    try {
      const resp = await saAuthFetch(
        `/login?next=${encodeURIComponent(nextRelative)}`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: $(this).serialize(),
        }
      );

      if (resp.ok) {
        const data = await resp.json().catch(() => ({}));

        if (data && data.success) {
          if (data.token) setToken(data.token);

          // Analytics succès
          if (window.SA && typeof SA.event === "function") {
            SA.event("auth_login_success", { method: "email_password" });
          }

          let redirect = data.redirect;
          if (!redirect) {
            let after = null;
            try {
              after = JSON.parse(
                sessionStorage.getItem("sa_post_login") || "null"
              );
            } catch {}
            redirect = nextRelative || after?.next || document.referrer || "/";
            if (!redirect.includes("#")) redirect += "#__sa_after_login";
          }

          // reset état local
          localStorage.removeItem("loginFailedAttempts");
          localStorage.removeItem("lastFailedTime");
          localStorage.removeItem("flash_closed");

          // modal success
          closePopup(); // ferme le loading
          showMessage("success", {
            text: data.message || "Welcome!",
            module: "Auth",
            autoCloseMs: 1200,
            closeOnBackdrop: true,
            closeOnSwipe: true,
          });

          setTimeout(() => {
            try {
              sessionStorage.removeItem("sa_post_login");
            } catch {}
            location.assign(redirect);
          }, 700);

          return; // stop ici
        }

        // Réponse non success
        if (window.SA && typeof SA.event === "function") {
          SA.event("auth_login_error", {
            method: "email_password",
            reason: data?.error || "unexpected_response",
          });
        }

        closePopup(); // ferme le loading
        showErrorMessage(data?.error || "Unexpected response.");
      } else {
        // HTTP non OK
        let data = null;
        try {
          data = await resp.json();
        } catch {}

        if (data && data.blocked) {
          failedAttempts = MAX_ATTEMPTS;
          localStorage.setItem("loginFailedAttempts", String(failedAttempts));
          localStorage.setItem("lastFailedTime", String(Date.now()));
          // Analytics
          if (window.SA && typeof SA.event === "function") {
            SA.event("auth_login_error", {
              method: "email_password",
              reason: "blocked",
            });
          }
          closePopup(); // ferme le loading
          showBlockedStatus(data.remaining || 10);
        } else {
          if (window.SA && typeof SA.event === "function") {
            SA.event("auth_login_error", {
              method: "email_password",
              reason: (data && data.error) || "http_error",
            });
          }
          closePopup(); // ferme le loading
          showErrorMessage(
            (data && data.error) ?? "An error occurred while sending the data."
          );
        }
      }
    } catch {
      // erreur réseau
      if (window.SA && typeof SA.event === "function") {
        SA.event("auth_login_error", {
          method: "email_password",
          reason: "network_error",
        });
      }
      closePopup(); // ferme le loading
      showErrorMessage("Network error.");
    } finally {
      submitting = false;
      setSubmitting(false);
      updateButtonAppearance();
    }
  });

  function updateButtonAppearance() {
    const $btn = $("#custom-login-login");
    if (failedAttempts >= 3) {
      $btn
        .css({
          "background-color": "#dc3545",
          "border-color": "#dc3545",
          color: "#fff",
        })
        .off("mouseenter mouseleave")
        .on("mouseenter", function () {
          $(this).css("opacity", ".8");
        })
        .on("mouseleave", function () {
          $(this).css("opacity", "1");
        });
    } else {
      $btn
        .css({ "background-color": "", "border-color": "", color: "" })
        .off("mouseenter mouseleave");
    }
  }

  function showBlockedStatus(minutes) {
    const $btn = $("#custom-login-login");
    $btn.prop("disabled", true).text(`Blocked (${minutes}m)`).css({
      "background-color": "#dc3545",
      "border-color": "#dc3545",
      color: "#fff",
    });

    showMessage("error", {
      text: `Too many attempts. Please try again in ${minutes} minute(s).`,
      module: "Auth",
      autoCloseMs: 0,
      closeOnBackdrop: true,
      closeOnSwipe: true,
    });
  }

  function showErrorMessage(error) {
    const text =
      typeof error === "object"
        ? Object.values(error).join(" • ")
        : error || "Error";
    showMessage("error", {
      text,
      module: "Auth",
      autoCloseMs: 0,
      closeOnBackdrop: true,
      closeOnSwipe: true,
    });
  }
});

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
