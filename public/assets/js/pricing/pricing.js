(() => {
  // ========= State =========
  let selectedPlan = "";
  let selectedPrice = "";
  let compressedFile = null;

  // ========= Utils message (branche sur ton système existant) =========
  function showMessage(kind, { text, onSuccess } = {}) {
    // Adapte à ton UI (toast/popup)
    console[kind === "error" ? "error" : "log"](text);
    if (kind === "success" && typeof onSuccess === "function") onSuccess();
  }

  // ========= Chips status helper =========
  function setStatusChip(statusText) {
    const el = document.getElementById("currentStatus");
    if (!el) return;

    // texte du chip
    el.textContent = statusText || "-";

    // classes
    el.classList.add("chip"); // base
    el.classList.remove(
      "chip--pending",
      "chip--approved",
      "chip--rejected",
      "chip--neutral"
    );

    const s = String(statusText || "").toLowerCase();
    if (s.includes("pending") || s.includes("attente")) {
      el.classList.add("chip--pending");
    } else if (
      s.includes("approved") ||
      s.includes("actif") ||
      s.includes("active")
    ) {
      el.classList.add("chip--approved");
    } else if (s.includes("rejected") || s.includes("rejet")) {
      el.classList.add("chip--rejected");
    } else {
      el.classList.add("chip--neutral");
    }

    // (optionnel) petite note sous le status
    const note = document.getElementById("currentStatusNote");
    if (!note) return;
    if (el.classList.contains("chip--pending")) {
      note.style.display = "block";
      note.textContent = "Votre preuve est en cours de vérification.";
    } else if (el.classList.contains("chip--approved")) {
      note.style.display = "block";
      note.textContent = "Votre offre est active.";
    } else if (el.classList.contains("chip--rejected")) {
      note.style.display = "block";
      note.textContent =
        "Votre preuve a été rejetée. Vous pouvez renvoyer une nouvelle preuve.";
    } else {
      note.style.display = "none";
    }
  }

  function updateStatusIcon(status) {
    const s = String(status || "").toLowerCase();
    const iconWrapper = document.getElementById("statusIcon");
    if (!iconWrapper) return;

    let svg = "";

    if (s === "approved" || s === "active" || s === "actif") {
      svg = `<svg viewBox="0 0 24 24" aria-hidden="true" fill="#28a745">
      <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm-1 14l-4-4 1.41-1.41L11 13.17l5.59-5.59L18 9l-7 7z"/>
    </svg>`;
    } else if (s === "pending" || s.includes("attente")) {
      svg = `<svg viewBox="0 0 24 24" aria-hidden="true" fill="#f59e0b">
      <path d="M6 2h12v2l-4 6v2l4 6v2H6v-2l4-6v-2L6 4V2z"/>
    </svg>`;
    } else if (s === "rejected" || s.includes("rejet")) {
      svg = `<svg viewBox="0 0 24 24" aria-hidden="true" fill="#dc3545">
      <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/>
    </svg>`;
    } else {
      svg = `<svg viewBox="0 0 24 24" aria-hidden="true" fill="#6b7280">
      <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
    </svg>`;
    }

    iconWrapper.innerHTML = svg;
  }

  // Normalise le statut pour l'affichage (ex: "active" => "approved")
  function normalizeStatusForUI(raw) {
    const s = String(raw || "").toLowerCase();
    if (s === "active") return "approved";
    return s; // pending, approved, rejected, etc.
  }

  // Choisit quoi afficher à partir de la payload API
  // Attendu : j.data.subscription (dernière demande) ET/OU j.data.active (abonnement actif)
  function pickDisplaySource(payloadData) {
    // compat: accepte les deux conventions
    const req =
      payloadData?.subscription || payloadData?.subscription_request || null;
    const act = payloadData?.active || payloadData?.current || null;

    // 1) S'il existe une demande non approuvée → on affiche (pending / rejected)
    if (req && String(req.status || "").toLowerCase() !== "approved") {
      return {
        plan: req.plan,
        status: req.status,
      };
    }

    // 2) Sinon, si un abonnement "current" existe → on affiche son statut effectif
    if (act) {
      const st = (act.effective_status || act.status || "").toLowerCase();
      return {
        plan: act.plan,
        status: st,
      };
    }

    // 3) Sinon, s'il y a une demande approuvée → on affiche approved
    if (req) {
      return {
        plan: req.plan,
        status: req.status,
      };
    }

    // 4) Rien à afficher
    return null;
  }

  // Affiche le statut dans la carte
  function displayCurrentSubscriptionFromPayload(data) {
    const chosen = pickDisplaySource(data);
    if (!chosen) return; // rien à afficher

    const plan = String(chosen.plan || "-");
    const status = normalizeStatusForUI(chosen.status);

    displayCurrentSubscription({
      plan,
      status,
    });
  }

  // ========= Modal open/close =========
  function subscribePlan(plan) {
    const planData = {
      basic: {
        text: "Offre <strong>Basique</strong> à 1 $ / mois",
        amount: "1 $",
      },
      pro: {
        text: "Offre <strong>Pro</strong> à 5 $ / mois",
        amount: "5 $",
      },
      premium: {
        text: "Offre <strong>Premium</strong> à 10 $ / mois",
        amount: "10 $",
      },
    };
    selectedPlan = plan;
    const info = planData[plan] || {
      text: "Offre sélectionnée",
      amount: "...",
    };
    selectedPrice = info.amount;

    document.getElementById("subscriptionPlanText").innerHTML = info.text;
    document.getElementById("subscriptionAmount").innerText = info.amount;
    document.getElementById("subscriptionModal").style.display = "block";

    const shopSection = document.querySelector("section.shop-section");
    if (shopSection) shopSection.classList.add("blurred");
  }
  window.subscribePlan = subscribePlan; // exposé pour les boutons HTML

  function closeSubscriptionModal() {
    const modal = document.getElementById("subscriptionModal");
    const content = modal.querySelector(".shop-popup-content");
    content.classList.add("closing");
    setTimeout(() => {
      modal.style.display = "none";
      content.classList.remove("closing");
    }, 350);
    const shopSection = document.querySelector("section.shop-section");
    if (shopSection) shopSection.classList.remove("blurred");
  }
  window.closeSubscriptionModal = closeSubscriptionModal;

  // ========= Grabber (fermeture par glissement) =========
  (function initGrabber() {
    const grabber = document.querySelector(".grabber");
    const modal = document.getElementById("subscriptionModal");
    if (!grabber || !modal) return;

    let startY = null,
      isDragging = false;

    const end = () => {
      isDragging = false;
      startY = null;
    };

    grabber.addEventListener("touchstart", (e) => {
      startY = e.touches[0].clientY;
      isDragging = true;
    });
    grabber.addEventListener("touchmove", (e) => {
      if (!isDragging || startY === null) return;
      const dy = e.touches[0].clientY - startY;
      if (dy > 80) {
        closeSubscriptionModal();
        end();
      }
    });
    grabber.addEventListener("touchend", end);

    grabber.addEventListener("mousedown", (e) => {
      startY = e.clientY;
      isDragging = true;
    });
    window.addEventListener("mousemove", (e) => {
      if (!isDragging || startY === null) return;
      const dy = e.clientY - startY;
      if (dy > 80) {
        closeSubscriptionModal();
        end();
      }
    });
    window.addEventListener("mouseup", end);
  })();

  // ========= Preview + compression image =========
  window.previewProof = function previewProof(ev) {
    const file = ev.target.files?.[0];
    const img = document.getElementById("proofPreview");
    const progress = document.getElementById("uploadProgress");
    if (!file) return;

    if (!file.type.startsWith("image/")) {
      showMessage("error", {
        text: "Fichier invalide. Veuillez sélectionner une image.",
      });
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      showMessage("error", {
        text: "L’image dépasse 2 Mo.",
      });
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      const imgElement = new Image();
      imgElement.onload = () => {
        const MAX_WIDTH = 800;
        const scale = Math.min(1, MAX_WIDTH / imgElement.width);
        const canvas = document.createElement("canvas");
        canvas.width = Math.round(imgElement.width * scale);
        canvas.height = Math.round(imgElement.height * scale);
        canvas
          .getContext("2d")
          .drawImage(imgElement, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(
          (blob) => {
            compressedFile = blob;
            const r2 = new FileReader();
            r2.onload = (e2) => {
              img.src = e2.target.result;
              img.style.display = "block";
            };
            r2.readAsDataURL(blob);
          },
          "image/jpeg",
          0.7
        );
      };
      imgElement.src = e.target.result;
    };
    reader.readAsDataURL(file);
  };

  // ========= Submit preuve (→ /api/seller/subscribe/manual) =========
  window.submitPaymentProof = function submitPaymentProof() {
    const btn = document.getElementById("confirmBtn");
    const progress = document.getElementById("uploadProgress");

    // Si tu utilises un token côté front, on tente de le récupérer
    const token =
      localStorage.getItem("access_token") ||
      localStorage.getItem("shop_token") ||
      null;

    if (!selectedPlan) {
      showMessage("error", {
        text: "Veuillez choisir une offre.",
      });
      return;
    }
    if (!compressedFile) {
      showMessage("error", {
        text: "Veuillez sélectionner une capture d’écran de paiement avant d’envoyer.",
      });
      return;
    }

    btn.disabled = true;
    btn.classList.add("sending");
    btn.innerHTML = `
      <svg viewBox="0 0 24 24" width="18" height="18" fill="#fff" style="vertical-align: middle; margin-right: 4px;">
        <path d="M4 4h16v16H4z"/>
      </svg>
      Envoi en cours...
    `;
    progress.style.display = "block";
    progress.value = 0;

    const formData = new FormData();
    formData.append("plan", selectedPlan);
    formData.append("proof", compressedFile, "proof.jpg");

    // XHR pour le suivi de progression
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "/api/seller/subscribe/manual");
    xhr.withCredentials = true; // cookies de session (PHP)
    if (token) xhr.setRequestHeader("Authorization", "Bearer " + token);

    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable)
        progress.value = Math.round((e.loaded / e.total) * 100);
    };

    xhr.onload = () => {
      resetUploadButton(btn, progress);

      let res;
      try {
        res = JSON.parse(xhr.responseText || "{}");
      } catch (_) {
        showMessage("error", {
          text: "Réponse illisible du serveur.",
        });
        return;
      }

      // Ta JsonResponse renvoie { success, message, data? }
      if (res.success) {
        showMessage("success", {
          text: res.message || "Preuve envoyée avec succès.",
          onSuccess: () => {
            closeSubscriptionModal();
            // Rafraîchir le statut
            loadCurrentSubscription();
          },
        });
      } else {
        const err =
          res.error || res.message || "Erreur lors de l’envoi de la preuve.";
        showMessage("error", {
          text: err,
        });
      }
    };

    xhr.onerror = () => {
      resetUploadButton(btn, progress);
      showMessage("error", {
        text: "Échec de connexion. Vérifiez votre réseau et réessayez.",
      });
    };

    xhr.send(formData);
  };

  function resetUploadButton(btn, progress) {
    btn.disabled = false;
    btn.classList.remove("sending");
    btn.innerHTML = "Envoyer la preuve";
    progress.style.display = "none";
    progress.value = 0;
  }

  // ========= Status courant (→ /api/seller/subscription/status) =========
  async function loadCurrentSubscription() {
    try {
      const res = await fetch("/api/seller/subscription/status", {
        method: "GET",
        credentials: "same-origin",
      });
      if (!res.ok) return;

      const j = await res.json(); // { success, data: { subscription?, active? } }
      if (!j.success || !j.data) return;

      // 👉 gère intelligemment (demande vs actif)
      displayCurrentSubscriptionFromPayload(j.data);
    } catch (e) {
      // silencieux
    }
  }

  function displayCurrentSubscription(data) {
    const currentBox = document.getElementById("currentSubscriptionStatus");
    const planEl = document.getElementById("currentPlan");

    const plan = String(data.plan || "");
    const status = String(data.status || ""); // déjà normalisé amont

    if (planEl) planEl.textContent = plan || "-";

    // Badge + icône
    setStatusChip(status);
    updateStatusIcon(status); // <= IMPORTANT (mettra le check vert si "approved")

    if (currentBox) {
      currentBox.hidden = false;
      currentBox.style.display = "block";
    }

    // Mise à jour des cartes (inchangé, juste s'assurer qu'on traite "approved"/"active")
    const allCards = document.querySelectorAll(".pricing-card");
    const lcPlan = plan.toLowerCase();
    const lcStatus = status.toLowerCase();

    allCards.forEach((card) => {
      const title = card.querySelector("h3");
      const btn = card.querySelector(".subscribe-button");
      const retry = document.querySelector(".choose-again-btn");
      const cardPlan = (title?.textContent || "").trim().toLowerCase();

      card.classList.remove("selected", "rejected", "disabled");
      if (btn) {
        btn.disabled = false;
        btn.classList.remove("disabled", "waiting");
        btn.innerText = "Souscrire maintenant";
        btn.removeAttribute("title");
      }
      if (retry) retry.style.display = "none";

      if (cardPlan.includes(lcPlan)) {
        if (lcStatus === "pending") {
          card.classList.add("selected");
          if (btn) {
            btn.disabled = true;
            btn.classList.add("disabled", "waiting");
            btn.innerHTML = `<i class="fas fa-hourglass-half" style="margin-right:6px;"></i> En attente...`;
          }
        } else if (lcStatus === "approved") {
          card.classList.add("selected");
          if (btn) {
            btn.disabled = true;
            btn.classList.add("disabled", "active");
            btn.innerText = "Déjà actif";
          }
        } else if (["rejected", "rejetée", "rejet"].includes(lcStatus)) {
          card.classList.add("rejected");
          if (btn) {
            btn.disabled = true;
            btn.classList.add("disabled");
            btn.innerText = "Rejetée";
          }
          if (retry) retry.style.display = "inline-block";
        }
      } else {
        // Si abonnement en cours/actif → désactiver les autres cartes
        if (["pending", "approved"].includes(lcStatus)) {
          card.classList.add("disabled");
          if (btn) {
            btn.disabled = true;
            btn.classList.add("disabled");
            btn.title = "Vous avez déjà une offre en cours.";
          }
        }
      }
    });
  }

  // ========= Retry depuis carte rejetée =========
  window.subscribePlanFromRejected = function (btn) {
    const card = btn.closest(".pricing-card");
    const title = card?.querySelector("h3");
    const planText = (title?.textContent || "").toLowerCase();
    if (planText.includes("basic")) subscribePlan("basic");
    else if (planText.includes("pro")) subscribePlan("pro");
    else if (planText.includes("premium")) subscribePlan("premium");
  };

  // ========= Copier numéros =========
  window.copyToClipboard = function (btn) {
    const number = btn.dataset.copy || btn.getAttribute("data-number");
    if (!number) return;
    navigator.clipboard
      .writeText(number)
      .then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        btn.disabled = true;
        setTimeout(() => {
          btn.innerHTML = original;
          btn.disabled = false;
        }, 1500);
      })
      .catch(() => {
        showMessage("error", {
          text: "Échec de la copie du numéro.",
        });
      });
  };

  // ========= Boot =========
  loadCurrentSubscription();
})();
