(() => {
  const modal = document.getElementById("app-modal");
  if (!modal) return;

  const panel = modal.querySelector(".mdl__panel");
  const titleEl = modal.querySelector(".mdl__title");
  const content = modal.querySelector(".mdl__content");
  const actions = modal.querySelector(".mdl__actions");

  let lastActive = null;
  let escHandler = null;

  function clear() {
    titleEl.textContent = "";
    content.innerHTML = "";
    actions.innerHTML = "";
    modal.classList.remove("mdl--danger", "mdl--success");
  }

  function closeModal() {
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    if (escHandler) {
      document.removeEventListener("keydown", escHandler);
      escHandler = null;
    }
    if (lastActive) {
      try {
        lastActive.focus();
      } catch (_) {}
      lastActive = null;
    }
  }

  function openModal({
    title = "",
    html = "",
    variant = "",
    buttons = [],
  } = {}) {
    clear();
    if (variant) modal.classList.add(`mdl--${variant}`);
    titleEl.textContent = title;
    content.innerHTML = html;

    // actions
    if (!buttons.length) {
      buttons = [
        {
          text: "Close",
          onClick: closeModal,
        },
      ];
    }
    buttons.forEach((b, i) => {
      const btn = document.createElement("button");
      btn.className = "mdl-btn " + (b.primary ? "mdl-btn--pri" : "");
      btn.type = "button";

      // choisir l'icône SVG
      let svgIcon = "";
      if (b.primary) {
        // check (validation)
        svgIcon = `
        <svg xmlns="http://www.w3.org/2000/svg" 
             class="mdl-btn__icon" width="18" height="18" 
             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>`;
      } else if (
        (b.text || "").toLowerCase().includes("cancel") ||
        (b.text || "").toLowerCase().includes("close")
      ) {
        // croix
        svgIcon = `
        <svg xmlns="http://www.w3.org/2000/svg" 
             class="mdl-btn__icon" width="18" height="18" 
             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>`;
      } else {
        // icône "ok" / chevron
        svgIcon = `
        <svg xmlns="http://www.w3.org/2000/svg" 
             class="mdl-btn__icon" width="18" height="18" 
             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>`;
      }

      // contenu bouton = icône + texte
      btn.innerHTML =
        svgIcon + `<span class="mdl-btn__text">${b.text || "OK"}</span>`;

      // callback
      btn.addEventListener("click", async () => {
        if (typeof b.onClick === "function") {
          try {
            const res = b.onClick();
            if (res instanceof Promise) await res;
          } catch (e) {
            /* no-op */
          }
        }
      });
      actions.appendChild(btn);
    });

    lastActive = document.activeElement;
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");

    // ESC / backdrop
    escHandler = (e) => {
      if (e.key === "Escape") closeModal();
    };
    document.addEventListener("keydown", escHandler);
  }

  // close by attributes
  modal.addEventListener("click", (e) => {
    if (e.target.closest("[data-modal-close]")) closeModal();
  });

  // Expose globally
  window.AppModal = {
    openModal,
    closeModal,
  };

  // Helper confirm (promesse)
  window.AppModal.confirm = ({
    title = "Confirmation",
    html = "",
    confirmText = "Confirm",
    cancelText = "Cancel",
    variant = "",
  } = {}) => {
    return new Promise((resolve, reject) => {
      openModal({
        title,
        html,
        variant,
        buttons: [
          {
            text: cancelText,
            onClick: () => {
              closeModal();
              reject(new Error("cancel"));
            },
          },
          {
            text: confirmText,
            primary: true,
            onClick: () => {
              closeModal();
              resolve(true);
            },
          },
        ],
      });
    });
  };

  // Helper alert (sans window.alert)
  window.AppModal.alert = ({
    title = "Info",
    html = "",
    variant = "",
  } = {}) => {
    openModal({
      title,
      html,
      variant,
      buttons: [
        {
          text: "OK",
          primary: true,
          onClick: closeModal,
        },
      ],
    });
  };
})();
document.addEventListener("DOMContentLoaded", () => {
  const btn = document.getElementById("btnBuyerConfirmDelivery");
  if (!btn) return;

  const msg = document.getElementById("buyerConfirmMsg");

  btn.addEventListener("click", async () => {
    if (!orderNo) {
      AppModal.alert({
        title: "Order not found",
        html: "Order number is missing.",
        variant: "danger",
      });
      return;
    }

    // Ask for confirmation
    try {
      await AppModal.confirm({
        title: "Confirm delivery?",
        html: "Do you confirm that you have <b>received the package</b>?",
        confirmText: "Yes, I received it",
        cancelText: "Cancel",
      });
    } catch {
      // canceled by user
      return;
    }

    btn.disabled = true;
    const prev = btn.textContent;
    btn.textContent = "Sending…";

    try {
      const fd = new FormData();
      fd.append("order_number", orderNo);

      const res = await fetch("/order/buyer/confirm-delivery", {
        method: "POST",
        body: fd,
        headers: {
          Accept: "application/json",
        },
      });

      const ct = (res.headers.get("content-type") || "").toLowerCase();
      const data = ct.includes("application/json")
        ? await res.json()
        : {
            success: false,
            message: "Non-JSON response",
          };

      if (!res.ok || !data?.success) {
        console.error("[buyerConfirmDelivery] res", res.status, data);
        throw new Error(data?.message || `HTTP ${res.status}`);
      }

      // Success
      if (msg) msg.style.display = "inline";
      btn.textContent = "Confirmed";
      btn.style.opacity = "0.7";

      AppModal.alert({
        title: "Thank you 🎉",
        html: "Your delivery confirmation has been recorded successfully.",
        variant: "success",
      });

      try {
        await fetchStatus();
      } catch (_) {}
    } catch (e) {
      AppModal.alert({
        title: "Action failed",
        html: `We could not confirm the delivery.<br><small>${
          e?.message || "Unknown error"
        }</small>`,
        variant: "danger",
      });
      btn.disabled = false;
      btn.textContent = prev;
    }
  });
});
document.addEventListener("DOMContentLoaded", () => {
  const buyerCard = document.getElementById("buyerDeliveryCard");
  if (!buyerCard) return;
  buyerCard.style.display = ["processing", "shipped"].includes(initialStatus)
    ? ""
    : "none";
});
(function () {
  "use strict";

  // ---------- COPY BUTTONS ----------
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".oc-copy");
    if (!btn) return;

    const text = btn.getAttribute("data-copy") || "";
    const kind = btn.dataset.kind || "account"; // 'account' ou 'amount'

    navigator.clipboard.writeText(text).then(() => {
      const prev = btn.textContent;
      btn.textContent =
        kind === "amount" ? "Amount copied!" : "Account copied!";
      btn.classList.add("copied");
      setTimeout(() => {
        btn.textContent = prev;
        btn.classList.remove("copied");
      }, 1200);
    });
  });

  // ---------- RECEIPT UPLOAD ----------
  const upZone = document.getElementById("upZone");
  const upInput = document.getElementById("upInput");
  const upPreview = document.getElementById("upPreview");
  const upBtn = document.getElementById("btnUpload");
  const upMsg = document.getElementById("upMsg");

  const hasBuyerUploadUI = !!(upZone && upInput && upPreview && upBtn && upMsg);

  // Source de vérité côté serveur (marche pour vendeur ET acheteur)
  // Pour l’UI acheteur, on lit le data-* si dispo, sinon on retombe sur ORDER_NO
  const orderNo = hasBuyerUploadUI
    ? upZone.dataset.orderNo || ORDER_NO
    : ORDER_NO;
  const LS_KEY = orderNo ? `sa:receipt:${orderNo}` : null;

  let selectedFile = null;

  // ---------- Helpers UI ----------
  function showMsg(type, text) {
    upMsg.className = "oc-hint";
    if (type === "ok") upMsg.classList.add("ok");
    if (type === "err") upMsg.classList.add("err");
    upMsg.textContent = text || "";
  }

  function setStep(n) {
    const steps = document.querySelectorAll(".oc-steps .oc-step");
    steps.forEach((s, i) => {
      s.classList.remove("done", "active");
      const idx = i + 1;
      if (idx < n) s.classList.add("done");
      if (idx === n) s.classList.add("active");
    });
  }

  function lockUploaderUI() {
    // vider input + variable + bloquer la zone + désactiver bouton
    if (upInput) upInput.value = "";
    selectedFile = null;
    upZone.classList.add("done");
    upBtn.disabled = true;
    upBtn.textContent = "Proof sent ✓";
  }

  function unlockUploaderUIForReplace() {
    upZone.classList.remove("done");
    upBtn.disabled = true; // sera activé quand un nouveau fichier est choisi
    upBtn.textContent = "Send proof";
    upPreview.innerHTML = "";
    showMsg("", "");
  }

  function renderLocalPreview(file) {
    const isPdf = file.type === "application/pdf";
    if (isPdf) {
      upPreview.innerHTML = `<span class="pdf">📄 ${file.name}</span>`;
    } else {
      const url = URL.createObjectURL(file);
      upPreview.innerHTML = `<img src="${url}" alt="preview">`;
    }
  }

  function successBanner(previewUrl, mime) {
    const isPdf = (mime || "").toLowerCase().includes("pdf");
    const html = `
      <div class="oc-alert success" role="status">
        <div class="ic">✔</div>
        <div>
          <div><b>Reçu envoyé.</b> Il est <b>bien reçu</b> et <b>en cours d’analyse</b>.</div>
          <div class="oc-hint">Tu peux revenir sur cette page plus tard pour vérifier si la validation est terminée.</div>
          ${
            previewUrl
              ? isPdf
                ? `<div class="oc-hint" style="margin-top:6px"><a href="${previewUrl}" target="_blank" rel="noopener">Voir le PDF</a></div>`
                : `<div class="up-preview" style="margin-top:8px"><img src="${previewUrl}" alt="Receipt preview"></div>`
              : ""
          }
        </div>
      </div>
      <div style="margin-top:8px;display:flex;gap:8px">
        <button id="btnReplace" type="button" class="oc-btn" style="background:#444;border-color:#444">Replace receipt</button>
      </div>
    `;
    upPreview.innerHTML = html;

    const rep = document.getElementById("btnReplace");
    if (rep) rep.addEventListener("click", unlockUploaderUIForReplace);
  }

  // ---------- Persistence (localStorage) ----------
  function persistSubmission(previewUrl, mime) {
    if (!LS_KEY) return;
    const payload = {
      t: Date.now(),
      previewUrl: previewUrl || "",
      mime: mime || "",
    };
    try {
      localStorage.setItem(LS_KEY, JSON.stringify(payload));
    } catch (_) {}
  }

  function readSubmission() {
    if (!LS_KEY) return null;
    try {
      const raw = localStorage.getItem(LS_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (_) {
      return null;
    }
  }

  function restoreSubmittedStateIfAny() {
    const saved = readSubmission();
    if (!saved) return;
    successBanner(saved.previewUrl, saved.mime);
    setStep(3);
    lockUploaderUI();
  }

  // ---------- API STATUS ----------
  async function fetchStatus() {
    if (!orderNo) return;

    const res = await fetch(
      `/order/receipt/status?no=${encodeURIComponent(orderNo)}`,
      {
        headers: {
          Accept: "application/json",
          "X-Order-Number": orderNo, // 👈 ceinture + bretelles
        },
      }
    );

    const ct = (res.headers.get("content-type") || "").toLowerCase();
    if (!ct.includes("application/json")) {
      const txt = await res.text();
      throw new Error("Unexpected response (not JSON).");
    }

    const data = await res.json();
    if (!res.ok || !data?.success) {
      throw new Error(data?.message || "Status fetch failed");
    }
    applyStatus(data);
  }

  function renderBannerFromAPI(banner) {
    const cont = upPreview;
    if (!cont) return;

    let cls = "info";
    if (banner?.type === "success") cls = "success";
    if (banner?.type === "warn") cls = "warn";

    const isPdf = (banner?.mime || "").toLowerCase().includes("pdf");
    const previewHtml = banner?.preview_url
      ? isPdf
        ? `<div class="oc-hint" style="margin-top:6px"><a href="${banner.preview_url}" target="_blank" rel="noopener">Voir le PDF</a></div>`
        : `<div class="up-preview" style="margin-top:8px"><img src="${banner.preview_url}" alt="Receipt preview"></div>`
      : "";

    cont.innerHTML = `
      <div class="oc-alert ${cls}" role="status">
        <div class="ic">${
          cls === "success" ? "✔" : cls === "warn" ? "⚠" : "ℹ"
        }</div>
        <div>
          <div><b>${banner?.title || ""}</b></div>
          <div class="oc-hint">${banner?.text || ""}</div>
          ${previewHtml}
        </div>
      </div>
    `;
  }

  function applyStatus(payload) {
    const lock = !!payload?.ui?.lock_upload;
    const step = Number(payload?.ui?.step || 2);
    const banner = payload?.ui?.banner || null;

    console.debug("[applyStatus] status=", payload?.order?.status, payload);

    setStep(step);
    if (banner) renderBannerFromAPI(banner);

    if (lock) {
      lockUploaderUI();
    } else {
      if (upZone) upZone.classList.remove("done");
      if (upBtn) {
        upBtn.disabled = true;
        upBtn.textContent = "Send proof";
      }
    }

    // bouton "J’ai reçu le colis"
    const buyerCard = document.getElementById("buyerDeliveryCard");
    if (buyerCard) {
      const liveStatus = String(payload?.order?.status || "").toLowerCase();
      const showIt = ["processing", "shipped"].includes(liveStatus);
      buyerCard.style.display = showIt ? "" : "none";
    }
  }

  if (hasBuyerUploadUI) {
    // ---------- Zone interactions ----------
    upZone.addEventListener("click", () => {
      if (upZone.classList.contains("done")) return;
      upInput.click();
    });

    upZone.addEventListener("dragover", (e) => {
      if (upZone.classList.contains("done")) return;
      e.preventDefault();
      upZone.classList.add("drag");
    });

    upZone.addEventListener("dragleave", () => upZone.classList.remove("drag"));

    upZone.addEventListener("drop", (e) => {
      if (upZone.classList.contains("done")) return;
      e.preventDefault();
      upZone.classList.remove("drag");
      const f =
        e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files[0] : null;
      if (!f) return;
      selectedFile = f;
      renderLocalPreview(f);
      showMsg("", "");
      upBtn.disabled = false; // activé après sélection
    });

    upInput.addEventListener("change", () => {
      const f = upInput.files && upInput.files[0] ? upInput.files[0] : null;
      if (!f) return;
      selectedFile = f;
      renderLocalPreview(f);
      showMsg("", "");
      upBtn.disabled = false; // activé après sélection
    });

    // ---------- Upload ----------
    upBtn.addEventListener("click", async () => {
      showMsg("", "");
      if (!orderNo) {
        showMsg("err", "Missing order number.");
        return;
      }
      if (!selectedFile) {
        showMsg("err", "Please choose a file (image/PDF).");
        return;
      }

      const prevTxt = upBtn.textContent;
      upBtn.disabled = true;
      upBtn.textContent = "Sending…";

      try {
        const fd = new FormData();
        fd.append("order_number", orderNo);
        fd.append("receipt", selectedFile);

        const res = await fetch("/order/receipt/upload", {
          method: "POST",
          body: fd,
        });
        const ct = (res.headers.get("content-type") || "").toLowerCase();

        let data;
        if (ct.includes("application/json")) {
          data = await res.json();
        } else {
          // Fallback HTML -> message lisible
          const txt = await res.text();
          const m = txt.match(/<title>([^<]+)<\/title>/i);
          const hint = m
            ? m[1]
            : txt.slice(0, 160) + (txt.length > 160 ? "…" : "");
          throw new Error(hint || "Unexpected response (HTML).");
        }

        if (!res.ok || !data?.success) {
          throw new Error(data?.message || "Upload failed.");
        }

        // Succès : bannière + étapes + verrouillage + persistance
        successBanner(data.preview_url, data.mime);
        setStep(3);
        lockUploaderUI();
        persistSubmission(data.preview_url, data.mime);
        showMsg(
          "ok",
          "Reçu bien envoyé. Analyse en cours. Reviens plus tard pour vérifier le statut."
        );

        // Synchronise avec la BD (source de vérité)
        try {
          await fetchStatus();
        } catch (_) {}
      } catch (err) {
        upBtn.disabled = false;
        upBtn.textContent = prevTxt;
        showMsg("err", err.message || "Upload failed.");
      }
    });
  }

  // ---------- SELLER CONFIRM ----------
  const btnSeller = document.getElementById("saSellerConfirmBtn");
  const msgSeller = document.getElementById("saSellerConfirmMsg");

  if (btnSeller && orderNoForSeller) {
    btnSeller.addEventListener("click", async () => {
      btnSeller.disabled = true;
      btnSeller.textContent = "Confirming…";
      try {
        const form = new FormData();
        form.append("order_number", orderNoForSeller);

        const res = await fetch("/order/seller/confirm", {
          method: "POST",
          body: form,
        });
        const j = await res.json().catch(() => null);
        if (!res.ok || !j || j.success !== true)
          throw new Error(j?.message || "Failed");

        if (msgSeller) msgSeller.style.display = "inline";
        btnSeller.textContent = "Confirmed";
        btnSeller.style.opacity = "0.7";

        // Optionnel: rafraîchir visuel status
        try {
          await fetchStatus();
        } catch (_) {}
      } catch (e) {
        alert("Unable to confirm: " + (e.message || "error"));
        btnSeller.disabled = false;
        btnSeller.textContent = "Confirm order";
      }
    });
  }

  // ----- Seller: live proof render -----
  const sellerProof = document.getElementById("sellerProof");

  async function fetchStatusForSeller() {
    if (!sellerProof || !orderNoGlobal) return;
    const res = await fetch(
      `/order/receipt/status?no=${encodeURIComponent(orderNoGlobal)}`,
      {
        headers: {
          Accept: "application/json",
          "X-Order-Number": orderNoGlobal,
        },
      }
    );
    const data = await res.json().catch(() => null);
    if (!res.ok || !data?.success) return;

    const latest = data?.receipt?.latest || null;
    if (!latest) {
      sellerProof.innerHTML = `<div class="oc-hint">No receipt uploaded yet.</div>`;
      return;
    }

    const isPdf = (latest.mime || "").toLowerCase().includes("pdf");
    sellerProof.innerHTML = `
    <div class="oc-hint">
      Status: <b>${latest.status || ""}</b>
      ${latest.note ? " — Note: " + latest.note : ""}
    </div>
    ${
      isPdf
        ? `<div class="oc-hint" style="margin-top:6px"><a href="${latest.path}" target="_blank" rel="noopener">Open PDF</a></div>`
        : `<div class="up-preview" style="margin-top:8px"><img src="${latest.path}" alt="Receipt preview"></div>`
    }
  `;
  }

  // Appelle au chargement + polling toutes les 30s (comme l’acheteur)
  fetchStatusForSeller().catch(() => {});
  setInterval(() => fetchStatusForSeller().catch(() => {}), 30000);

  // ---------- Init ----------
  if (hasBuyerUploadUI) {
    upBtn.disabled = true;
    restoreSubmittedStateIfAny();
    fetchStatus().catch(() => {});
    setInterval(() => {
      fetchStatus().catch(() => {});
    }, 30000);
  }

  // Côté vendeur uniquement :
  fetchStatusForSeller().catch(() => {});
  setInterval(() => {
    fetchStatusForSeller().catch(() => {});
  }, 30000);

  const btnRefresh = document.getElementById("btnRefresh");
  if (btnRefresh)
    btnRefresh.addEventListener("click", () => fetchStatus().catch(() => {}));

  // (Optionnel) Petit polling toutes les 30s pour voir si l’admin a validé
  setInterval(() => {
    fetchStatus().catch(() => {});
  }, 30000);
})();
