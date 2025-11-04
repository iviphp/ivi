// Pays proposés (tu peux en ajouter si besoin)
const COUNTRY_OPTIONS = [
  {
    code: "UG",
    name: "Uganda",
  },
  {
    code: "CD",
    name: "Congo DRC",
  },
  {
    code: "KE",
    name: "Kenya",
  },
  {
    code: "RW",
    name: "Rwanda",
  },
  {
    code: "TZ",
    name: "Tanzania",
  },
  {
    code: "BI",
    name: "Burundi",
  },
];

// Devises proposées (liste concise + USD/EUR)
const CURRENCY_OPTIONS = [
  {
    code: "UGX",
    name: "Ugandan Shilling",
  },
  {
    code: "CDF",
    name: "Congolese Franc",
  },
  {
    code: "KES",
    name: "Kenyan Shilling",
  },
  {
    code: "RWF",
    name: "Rwandan Franc",
  },
  {
    code: "TZS",
    name: "Tanzanian Shilling",
  },
  {
    code: "BIF",
    name: "Burundian Franc",
  },
  {
    code: "USD",
    name: "US Dollar",
  },
  {
    code: "EUR",
    name: "Euro",
  },
];

// Mapping par pays → devise par défaut
const DEFAULT_CURRENCY_BY_COUNTRY = {
  UG: "UGX",
  CD: "CDF",
  KE: "KES",
  RW: "RWF",
  TZ: "TZS",
  BI: "BIF",
};

// Mapping Provider → (country, currency)
const PROVIDER_DEFAULTS = {
  MTN: {
    country: "UG",
    currency: "UGX",
  },
  AIRTEL: {
    country: "UG",
    currency: "UGX",
  },
  MPESA: {
    country: "CD",
    currency: "CDF",
  },
  BANK: {
    country: "",
    currency: "",
  },
  CARD: {
    country: "",
    currency: "",
  },
};

(function () {
  const LIST = document.getElementById("payments-list");
  const FORM = document.getElementById("payment-form");
  const BTN = document.getElementById("savePaymentBtn");
  const CSRF = FORM.querySelector('[name="csrf_token"]')?.value || "";

  const API_LIST = "/api/payments";
  const API_CREATE = "/api/payments";
  const API_DEF = (id) => `/api/payments/${id}/default`;
  const API_DEL = (id) => `/api/payments/${id}`;

  /* ====== Toggle "Add a method" form ====== */
  (function setupAddMethodToggle() {
    // cacher le formulaire par défaut
    FORM.classList.add("is-hidden");

    function showForm() {
      FORM.classList.remove("is-hidden");
      initCountryCurrencySelects();
      const first = FORM.querySelector("input, select, textarea, button");
      if (first)
        first.focus({
          preventScroll: true,
        });
      FORM.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    }

    function hideForm() {
      FORM.classList.add("is-hidden");
    }

    // délégation clics : tous les liens/boutons qui pointent vers #payment-form
    document.addEventListener("click", (e) => {
      const btn = e.target.closest(
        'a[href="#payment-form"], button[data-toggle="payment-form"]'
      );
      if (!btn) return;
      e.preventDefault();
      showForm();
    });

    // refermer le formulaire après un enregistrement réussi
    FORM.addEventListener("sa:save-success", () => hideForm());

    // exposer les helpers globalement si besoin
    window.__paymentsFormShow = showForm;
    window.__paymentsFormHide = hideForm;
  })();

  /* ------- Modal helpers ------- */
  function toastLoading(text = "Traitement en cours…") {
    showMessage("loading", {
      text,
      module: "Payments",
      autoCloseMs: 0,
      closeOnBackdrop: false,
    });
  }

  function toastSuccess(text = "Opération réussie", onSuccess = null) {
    showMessage("success", {
      text,
      module: "Payments",
      onSuccess,
      autoCloseMs: 2000,
    });
  }

  function toastError(text = "Une erreur est survenue") {
    showMessage("error", {
      text,
      module: "Payments",
      autoCloseMs: 0,
      closeOnBackdrop: false,
    });
  }

  /* ------- Utils ------- */
  function setSelectOptions(selectEl, options, placeholder = "Select") {
    selectEl.innerHTML = "";
    const ph = document.createElement("option");
    ph.value = "";
    ph.textContent = placeholder;
    ph.disabled = true;
    ph.selected = true;
    selectEl.appendChild(ph);
    options.forEach((o) => {
      const opt = document.createElement("option");
      opt.value = o.code;
      opt.textContent = `${o.name} (${o.code})`;
      selectEl.appendChild(opt);
    });
  }

  function setSelectValue(selectEl, value) {
    if (!value) return;
    const opt = Array.from(selectEl.options).find((o) => o.value === value);
    if (opt) {
      selectEl.value = value;
      opt.selected = true;
    }
  }

  function initCountryCurrencySelects() {
    const countrySel = document.getElementById("country_code");
    const currencySel = document.getElementById("currency");
    setSelectOptions(countrySel, COUNTRY_OPTIONS, "Select a country");
    setSelectOptions(currencySel, CURRENCY_OPTIONS, "Select currency");

    // Auto change de devise quand on change de pays (si devise vide ou incohérente)
    countrySel.addEventListener("change", () => {
      const country = countrySel.value;
      const defaultCur = DEFAULT_CURRENCY_BY_COUNTRY[country];
      if (defaultCur) setSelectValue(currencySel, defaultCur);
    });

    // Pré-sélection douce (si provider déjà choisi)
    const provider = document.getElementById("provider").value;
    applyProviderDefaults(provider);
  }

  function applyProviderDefaults(provider) {
    const countrySel = document.getElementById("country_code");
    const currencySel = document.getElementById("currency");
    const typeSel = document.getElementById("account_type");

    const def = PROVIDER_DEFAULTS[provider] || {};
    if (def.country) setSelectValue(countrySel, def.country);
    if (def.currency) setSelectValue(currencySel, def.currency);
    if (typeSel)
      typeSel.value =
        provider === "BANK" || provider === "CARD" ? provider : "MOBILE_MONEY";

    // Si le pays est renseigné mais pas la devise, applique la devise par défaut du pays
    if (countrySel.value && !currencySel.value) {
      const cur = DEFAULT_CURRENCY_BY_COUNTRY[countrySel.value];
      if (cur) setSelectValue(currencySel, cur);
    }
  }

  // -------- Provider defaults (selects) --------
  document.getElementById("provider").addEventListener("change", (e) => {
    applyProviderDefaults(e.target.value);
  });

  /* -------- JSON parser (JsonResponse compatible) -------- */
  async function parseJsonSafe(res) {
    const ct = res.headers.get("content-type") || "";
    const text = await res.text();
    if (!ct.includes("application/json")) {
      console.error("Non-JSON response:", text.slice(0, 500));
      throw new Error("Le serveur n’a pas renvoyé du JSON.");
    }
    let json;
    try {
      json = JSON.parse(text);
    } catch (e) {
      console.error("JSON parse failed:", text.slice(0, 500));
      throw e;
    }
    if (!res.ok || json.success === false) {
      throw new Error(
        (json && (json.error || json.message)) || `HTTP ${res.status}`
      );
    }
    return json;
  }

  function escapeHtml(s) {
    return (s || "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        }[c])
    );
  }

  /* -------- Load list -------- */
  async function load() {
    // skeleton
    LIST.innerHTML = `
    <div class="vp-skeleton">
      <div class="vp-skel-avatar"></div>
      <div class="vp-skel-lines"><span class="line"></span><span class="line w-60"></span></div>
      <div class="vp-skel-actions"></div>
    </div>`;

    try {
      const res = await fetch(API_LIST, {
        headers: {
          Accept: "application/json",
        },
        credentials: "same-origin",
      });
      const json = await parseJsonSafe(res);
      const items = json.data && json.data.items ? json.data.items : [];

      // --- AUCUN MOYEN DE PAIEMENT ---
      if (!items.length) {
        const emptyTpl = document.getElementById("payments-empty");
        if (emptyTpl) {
          const clone = emptyTpl.cloneNode(true);
          clone.style.display = "block"; // rendre visible
          clone.id = ""; // éviter doublon d'ID
          LIST.innerHTML = "";
          LIST.appendChild(clone);
        } else {
          LIST.innerHTML = `
          <div class="vp-empty">
            <div class="vp-empty__icon">💳</div>
            <p class="vp-empty__text">
              You haven’t added any payment methods yet.<br />
              <strong>Softadastra’s default methods</strong> will be used for you until you add your own.
            </p>
            <div style="margin-top:.5rem;">
              <a href="#payment-form" class="vp-btn vp-btn--primary">Add a method</a>
            </div>
          </div>`;
        }

        // defaults Softadastra (read-only)
        const defs =
          json.data && json.data.platform_defaults
            ? json.data.platform_defaults
            : [];
        if (defs.length) {
          const html = defs
            .map(
              (d) => `
          <div class="sa-card sa-card--ghost">
            <div class="sa-card__left">
              <span class="sa-badge">${d.provider}</span>
              <div>
                <div><strong>${escapeHtml(
                  d.account_label
                )}</strong> <em>(Softadastra)</em></div>
                <div>${escapeHtml(d.account_value)} · ${d.currency || ""} ${
                d.country_code ? "· " + d.country_code : ""
              }</div>
                <div><span class="sa-status">${d.status}</span></div>
              </div>
            </div>
          </div>`
            )
            .join("");
          const wrap = document.createElement("div");
          wrap.style.marginTop = "10px";
          wrap.innerHTML = html;
          LIST.appendChild(wrap);
        }

        // ✅ garantit le bouton "Add a method" même en empty state
        try {
          ensureAddBtn();
        } catch {}
        return;
      }

      // --- LISTE REMPLIE ---
      LIST.innerHTML = items
        .map((item) => {
          const def = item.is_default
            ? '<span class="sa-badge sa-badge--default">Default</span>'
            : "";
          const st = `<span class="sa-status">${item.status}</span>`;
          return `
        <div class="sa-card" data-id="${item.id}">
          <div class="sa-card__left">
            <span class="sa-badge">${item.provider}</span>
            <div>
              <div><strong>${escapeHtml(item.account_label)}</strong></div>
              <div>${escapeHtml(item.account_value)} · ${item.currency || ""} ${
            item.country_code ? "· " + item.country_code : ""
          }</div>
              <div>${st}</div>
            </div>
          </div>
          <div class="sa-actions">
            ${
              !item.is_default
                ? `<button class="sa-btn" data-act="default">Set default</button>`
                : ""
            }
            <button class="sa-btn sa-btn--danger" data-act="delete">Delete</button>
          </div>
          ${def}
        </div>`;
        })
        .join("");

      // ✅ garantit le bouton "Add a method" quand il y a 1 ou N items
      try {
        ensureAddBtn();
      } catch {}
    } catch (err) {
      console.error(err);
      toastError("Échec du chargement des moyens de paiement.");
      LIST.innerHTML =
        '<div class="vp-empty"><p style="color:#b91c1c;">Load failed.</p></div>';
      // ✅ même en erreur, on tente d’afficher le bouton
      try {
        ensureAddBtn();
      } catch {}
    }
  }

  /* -------- Normalisation -------- */
  function fmtValue(v) {
    v = (v || "").trim();
    const digits = v.replace(/\D+/g, "");
    if (v.startsWith("+")) return "+" + digits;
    if (/^0\d{9}$/.test(digits)) return "+256" + digits.slice(1);
    return digits ? "+" + digits : "";
  }

  /* -------- Actions POST only -------- */
  LIST.addEventListener("click", async (e) => {
    const btn = e.target.closest("button[data-act]");
    if (!btn) return;
    const card = btn.closest(".sa-card");
    const id = card?.dataset.id;
    if (!id) return;

    btn.disabled = true;
    try {
      if (btn.dataset.act === "default") {
        toastLoading("Définition du moyen de paiement par défaut…");
        const fd = new FormData();
        fd.set("csrf_token", CSRF);
        const res = await fetch(API_DEF(id), {
          method: "POST",
          body: fd,
          headers: {
            Accept: "application/json",
          },
          credentials: "same-origin",
        });
        await parseJsonSafe(res);
        closePopup();
        toastSuccess("Moyen par défaut défini.", async () => {
          await load();
        });
      } else if (btn.dataset.act === "delete") {
        toastLoading("Suppression en cours…");
        const fd = new FormData();
        fd.set("csrf_token", CSRF);
        fd.set("_method", "DELETE");
        const res = await fetch(API_DEL(id), {
          method: "POST",
          body: fd,
          headers: {
            Accept: "application/json",
            "X-HTTP-Method-Override": "DELETE",
          },
          credentials: "same-origin",
        });
        await parseJsonSafe(res);
        closePopup();
        toastSuccess("Méthode supprimée.", async () => {
          await load();
        });
      }
    } catch (err) {
      console.error(err);
      closePopup();
      toastError(err?.message || "Action échouée.");
    } finally {
      btn.disabled = false;
    }
  });

  /* -------- Submit (create/update via upsert POST) -------- */
  FORM.addEventListener("submit", async (e) => {
    e.preventDefault();
    BTN.disabled = true;

    const f = new FormData(FORM);
    f.set("account_value", fmtValue(f.get("account_value") || ""));

    try {
      toastLoading("Enregistrement du moyen de paiement…");
      const res = await fetch(API_CREATE, {
        method: "POST",
        body: f,
        headers: {
          Accept: "application/json",
        },
        credentials: "same-origin",
      });
      const json = await parseJsonSafe(res);
      FORM.reset();
      closePopup();
      toastSuccess(json.message || "Enregistré.", async () => {
        await load();
        FORM.dispatchEvent(new Event("sa:save-success"));
      });

      // Re-initialise les selects après reset
      initCountryCurrencySelects();
    } catch (err) {
      console.error(err);
      closePopup();
      toastError(err?.message || "Échec de l’enregistrement.");
    } finally {
      BTN.disabled = false;
    }
  });

  // Init selects + page
  initCountryCurrencySelects();
  load();
})();
