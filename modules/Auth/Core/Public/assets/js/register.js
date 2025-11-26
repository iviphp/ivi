document.addEventListener("DOMContentLoaded", function () {
  const flagIcon = document.getElementById("flag-icon");
  const countryDropdown = document.getElementById("country-dropdown");
  /* === Example usage:
showMessage("loading", { module: "Register", text: "Processing..." });
showMessage("error",   { module: "Register", text: "Password too short." });
// success + redirect after close:
showMessage("success", { text: "Account created!", onSuccess: () => location.href = "/auth/sync" });
*/

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

  // --- Form submit (jQuery) ---
  $(function () {
    $("#registerForm").on("submit", function (event) {
      event.preventDefault();

      // ✅ mettre à jour CSRF avant envoi
      updateCsrfToken();

      const $submitBtn = $("#registerForm button[type='submit']");
      let $spinner = $submitBtn.find(".btn-spinner");

      if (!$spinner.length) {
        $spinner = $(
          "<span class='btn-spinner' style='display:none'></span>"
        ).appendTo($submitBtn);
      }

      const $btnText = $submitBtn.find(".btn-text");

      // ANALYTICS – tentative d’inscription (email+password)
      if (window.SA && typeof SA.event === "function") {
        SA.event("auth_register_submit", { method: "email+password" });
      }

      $submitBtn.prop("disabled", true);
      $btnText.hide();
      $spinner.show();

      const formData = $(this).serialize();

      $.ajax({
        url: "/auth/register",
        type: "POST",
        data: formData,
        dataType: "json",

        success: function (data, textStatus, jqXHR) {
          $spinner.hide();
          $btnText.show();
          $submitBtn.prop("disabled", false);

          // Succès → HTTP 201 et aucune erreur
          if (
            jqXHR.status === 201 &&
            Array.isArray(data.errors) &&
            data.errors.length === 0
          ) {
            if (window.SA && typeof SA.event === "function") {
              SA.event("auth_register_success", {
                method: "email+password",
              });
            }

            localStorage.setItem("justRegistered", "true");
            const to = data?.redirect || "/auth/sync";

            showMessage("success", {
              text:
                data?.message || "Your account has been created successfully.",
              onSuccess: () => {
                window.location.href = to;
                console.log("Registration succeeded, redirection skipped.");
              },
            });
            return;
          }

          // Sinon → erreurs
          const errs = data.errors || readErrors(data);
          showFieldErrors(errs);

          if (window.SA && typeof SA.event === "function") {
            SA.event("auth_register_error", {
              method: "email+password",
              reason: "validation",
              fields: errs ? Object.keys(errs) : null,
            });
          }

          const friendly = buildFriendlyErrorText(
            errs,
            data.message || "Please fix the highlighted fields."
          );

          showMessage("error", {
            text: friendly,
            autoCloseMs: 0,
            closeOnBackdrop: false,
            closeOnSwipe: false,
          });
        },

        error: function (xhr) {
          $spinner.hide();
          $btnText.show();
          $submitBtn.prop("disabled", false);

          let payload;
          try {
            payload = xhr.responseJSON || JSON.parse(xhr.responseText);
          } catch {
            payload = { error: xhr.responseText };
          }

          const errs = payload.errors || readErrors(payload);
          showFieldErrors(errs);

          if (window.SA && typeof SA.event === "function") {
            SA.event("auth_register_error", {
              method: "email+password",
              reason: "http_error",
              status: xhr.status || null,
            });
          }

          const friendly = buildFriendlyErrorText(
            errs,
            payload.message || "Server error."
          );

          showMessage("error", {
            text: friendly,
            autoCloseMs: 0,
            closeOnBackdrop: false,
            closeOnSwipe: false,
          });
        },
      });

      /* ============== Helpers (restent inchangés) ============== */

      const FIELD_MAP = {
        fullname: "#fullname",
        email: "#email",
        password: "#password",
      };

      function readTitle(payload) {
        if (!payload) return "";
        return payload.message || payload.error || payload.reason || "";
      }

      function readErrors(payload) {
        if (!payload) return null;
        return payload.errors || payload.data?.errors || null;
      }

      function humanizeFieldName(key) {
        switch (key) {
          case "fullname":
            return "Full name";
          case "email":
            return "Email address";
          case "password":
            return "Password";
          default:
            return (key || "")
              .replace(/_/g, " ")
              .replace(/\b\w/g, (m) => m.toUpperCase());
        }
      }

      function flattenErrorLines(errs) {
        const lines = [];
        if (!errs || typeof errs !== "object") return lines;
        for (const key in errs) {
          const label = humanizeFieldName(key);
          const val = errs[key];
          if (Array.isArray(val)) {
            val.forEach((v) => v && lines.push(`${label}: ${String(v)}`));
          } else if (val) {
            lines.push(`${label}: ${String(val)}`);
          }
        }
        return lines;
      }

      function buildFriendlyErrorText(
        errs,
        heading = "Please review your entries."
      ) {
        const lines = flattenErrorLines(errs);
        if (!lines.length) return heading;
        return `${heading}\n\n- ${lines.join("\n- ")}`;
      }

      function showFieldErrors(errs) {
        let first = null;

        Object.values(FIELD_MAP).forEach((sel) => {
          const el = document.querySelector(sel);
          if (el) el.classList.remove("input-error");
        });

        if (!errs || typeof errs !== "object") return;

        for (const key in errs) {
          const sel = FIELD_MAP[key];
          const el = sel ? document.querySelector(sel) : null;
          if (el) {
            el.classList.add("input-error");
            if (!first) first = el;
          }
        }

        if (first && typeof first.focus === "function") {
          first.focus();
        }
      }
    });
  });

  // Fermer le popup
  $("#closePopup").on("click", function () {
    $("#popupMessage").hide();
  });

  // Toggle password
  const toggleBtn = document.getElementById("togglePassword");
  const pwd = document.getElementById("password");
  if (!toggleBtn || !pwd) return;

  const icon = toggleBtn.querySelector("i"); // <i class="fa fa-eye">

  toggleBtn.addEventListener("click", function (e) {
    e.preventDefault();
    const show = pwd.type === "password";
    pwd.type = show ? "text" : "password";

    // met à jour l’icône et l’accessibilité
    if (icon) {
      icon.classList.toggle("fa-eye", !show);
      icon.classList.toggle("fa-eye-slash", show);
    }
    toggleBtn.setAttribute(
      "aria-label",
      show ? "Hide password" : "Show password"
    );
    toggleBtn.setAttribute("aria-pressed", String(show));
  });
  function formatErrorObject(err) {
    if (typeof err === "string") return err;
    if (typeof err === "object") {
      const lines = [];
      for (const k in err) lines.push(err[k]);
      return lines.join("<hr>");
    }
    try {
      return JSON.stringify(err);
    } catch {
      return "Unknown error.";
    }
  }
});
