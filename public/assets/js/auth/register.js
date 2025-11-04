document.addEventListener("DOMContentLoaded", function () {
  const phoneInput = document.getElementById("phone_number");
  const phoneError = document.getElementById("phone_number_error");
  const flagIcon = document.getElementById("flag-icon");
  const countryDropdown = document.getElementById("country-dropdown");
  const phoneWrapper = document.getElementById("phone-wrapper");

  // --- Helpers ---
  const onlyDigits = (s) => (s || "").replace(/\D/g, "");
  const trimSpaces = (s) => (s || "").replace(/\s+/g, "");

  // Normalise vers E.164 (+2567xxxxxxxx ou +243xxxxxxxxx)
  function normalizePhoneE164(raw) {
    if (!raw) return "";

    let v = raw.trim();

    // Remplace espaces/traits
    v = v.replace(/[^\d+]/g, "");

    // Si déjà +256... ou +243... → nettoie
    if (v.startsWith("+256")) {
      const d = onlyDigits(v.slice(1)); // sans '+'
      return "+256" + d.slice(3, 12); // garde 9 chiffres après 256
    }
    if (v.startsWith("+243")) {
      const d = onlyDigits(v.slice(1));
      return "+243" + d.slice(3, 12); // 9 chiffres après 243
    }

    // Uganda: 256..., 07..., 7...
    if (v.startsWith("256")) {
      const d = onlyDigits(v).slice(3);
      return "+256" + d.slice(0, 9);
    }
    if (v.startsWith("07")) {
      const d = onlyDigits(v).slice(1); // drop leading 0 → 7xxxxxxxx
      return "+256" + d.slice(0, 9);
    }
    if (v.startsWith("7")) {
      const d = onlyDigits(v);
      return "+256" + d.slice(0, 9);
    }

    // DRC: 243..., 0[89]..., [89]...
    if (v.startsWith("243")) {
      const d = onlyDigits(v).slice(3);
      return "+243" + d.slice(0, 9);
    }
    if (/^0[89]/.test(v)) {
      const d = onlyDigits(v).slice(1); // drop leading 0
      return "+243" + d.slice(0, 9);
    }
    if (/^[89]/.test(v)) {
      const d = onlyDigits(v);
      return "+243" + d.slice(0, 9);
    }

    // Par défaut: si 12 ou 13 chiffres avec indicatif deviné (rare) → garde 12 après indicatif
    const d = onlyDigits(v);
    if (d.length === 12 && d.startsWith("256")) return "+256" + d.slice(3);
    if (d.length === 12 && d.startsWith("243")) return "+243" + d.slice(3);

    return raw; // fallback: retourne tel quel (pour afficher l'erreur)
  }

  function isValidUG(msisdn) {
    return /^\+2567\d{8}$/.test(msisdn); // Uganda: +256 7XXXXXXXX
  }
  function isValidCD(msisdn) {
    return /^\+243\d{9}$/.test(msisdn); // DRC: +243 XXXXXXXXX (9 chiffres)
  }

  function updateFlag(msisdn) {
    if (msisdn.startsWith("+256")) flagIcon.textContent = "🇺🇬";
    else if (msisdn.startsWith("+243")) flagIcon.textContent = "🇨🇩";
    else flagIcon.textContent = "";
  }

  // Affiche drapeau dynamiquement
  phoneInput.addEventListener("input", function () {
    const norm = normalizePhoneE164(phoneInput.value);
    updateFlag(norm);
  });

  // Dropdown pays
  phoneInput.addEventListener("focus", function () {
    countryDropdown.style.display = "block";
  });

  document.addEventListener("click", function (e) {
    if (!phoneWrapper.contains(e.target)) {
      countryDropdown.style.display = "none";
    }
  });

  document.querySelectorAll(".country-option").forEach((option) => {
    option.addEventListener("click", function (e) {
      e.stopPropagation();
      const code = this.dataset.code;
      const flag = this.dataset.flag;
      phoneInput.value = code + " ";
      flagIcon.textContent = flag;
      countryDropdown.style.display = "none";
      phoneError.textContent = "";
      phoneInput.classList.remove("input-error");
    });
  });

  /* === Example usage:
showMessage("loading", { module: "Register", text: "Processing..." });
showMessage("error",   { module: "Register", text: "Password too short." });
// success + redirect after close:
showMessage("success", { text: "Account created!", onSuccess: () => location.href = "/auth/sync" });
*/

  // --- Form submit (jQuery) ---
  $("#registerForm").on("submit", function (event) {
    event.preventDefault();

    const $submitBtn = $("#custom-login-login");
    const $spinner = $submitBtn.find(".btn-spinner");
    const $btnText = $submitBtn.find(".btn-text");
    const emailGroup = document.getElementById("email-group");

    // Normalise avant d'envoyer
    const raw = phoneInput.value;
    const e164 = normalizePhoneE164(raw);
    phoneInput.value = e164; // on poste la version normalisée
    updateFlag(e164);

    // ✅ ANALYTICS: tentative d'inscription (avant validation)
    if (window.SA && typeof SA.event === "function") {
      SA.event("auth_register_submit", { method: "email+password+phone" });
    }

    const valid = isValidUG(e164) || isValidCD(e164);
    if (!valid) {
      phoneError.textContent =
        "Enter a valid number: +256 7XXXXXXXX (Uganda) or +243 XXXXXXXXX (DRC)";
      phoneInput.classList.add("input-error");
      emailGroup.style.marginTop = "50px";
      $submitBtn.prop("disabled", false);
      $spinner.hide();
      $btnText.show();

      // ✅ ANALYTICS: erreur de validation côté client (téléphone)
      if (window.SA && typeof SA.event === "function") {
        SA.event("auth_register_error", {
          method: "email+password+phone",
          reason: "invalid_phone",
        });
      }
      return;
    } else {
      phoneError.textContent = "";
      phoneInput.classList.remove("input-error");
      emailGroup.style.marginTop = "";
    }

    $submitBtn.prop("disabled", true);
    $btnText.hide();
    $spinner.show();

    const formData = $(this).serialize();

    $.ajax({
      url: "/register",
      type: "POST",
      data: formData,
      dataType: "json",

      success: function (data, textStatus, jqXHR) {
        $spinner.hide();
        $btnText.show();
        $submitBtn.prop("disabled", false);

        const isCreated =
          jqXHR.status === 201 || !!data?.token || !!data?.redirect;

        if (isCreated) {
          // ✅ ANALYTICS: succès d'inscription
          if (window.SA && typeof SA.event === "function") {
            SA.event("auth_register_success", {
              method: "email+password+phone",
            });
          }

          localStorage.setItem("justRegistered", "true");
          const to = data?.redirect || "/auth/sync";
          showMessage("success", {
            text:
              data?.message || "Your account has been created successfully.",
            onSuccess: () => (window.location.href = to),
          });
          return;
        }

        // 200 mais pas created → erreurs métier (validation serveur)
        const errs = readErrors(data);
        showFieldErrors(errs);

        // ✅ ANALYTICS: erreur de validation serveur (sans PII)
        if (window.SA && typeof SA.event === "function") {
          SA.event("auth_register_error", {
            method: "email+password+phone",
            reason: "validation",
            // on envoie UNIQUEMENT la liste des champs en cause
            fields: errs ? Object.keys(errs) : null,
          });
        }

        const friendly = buildFriendlyErrorText(
          errs,
          readTitle(data) || "Please fix the highlighted fields."
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

        const errs = readErrors(payload);
        showFieldErrors(errs);

        // ✅ ANALYTICS: erreur HTTP (serveur)
        if (window.SA && typeof SA.event === "function") {
          SA.event("auth_register_error", {
            method: "email+password+phone",
            reason: "http_error",
            status: xhr.status || null,
          });
        }

        const friendly = buildFriendlyErrorText(errs, readTitle(payload));
        showMessage("error", {
          text: friendly,
          autoCloseMs: 0,
          closeOnBackdrop: false,
          closeOnSwipe: false,
        });
      },
    });

    /* ============== Helpers “UX friendly” (inchangés) ============== */

    const FIELD_MAP = {
      fullname: "#fullname",
      email: "#email",
      password: "#password",
      phone_number: "#phone_number",
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
        case "phone_number":
          return "WhatsApp number";
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
      if (first && typeof first.focus === "function") first.focus();
    }
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
