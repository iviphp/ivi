/* =========================
   API helpers (GET/PUT/DELETE)
   ========================= */
function authHeaders() {
  const token = localStorage.getItem("sa_access_token");
  const h = { Accept: "application/json" };
  if (token) h["Authorization"] = `Bearer ${token}`;
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta?.content) h["X-CSRF-Token"] = meta.content;
  return h;
}

async function apiGet(url) {
  const r = await fetch(url, {
    method: "GET",
    credentials: "include",
    headers: authHeaders(),
  });
  if (r.status === 401) {
    window.location.href = "/login";
    throw new Error("Unauthorized");
  }
  const j = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(j?.error?.message || j?.error || "Request failed");
  return j;
}

async function apiPut(url, body) {
  const r = await fetch(url, {
    method: "PUT",
    credentials: "include",
    headers: { ...authHeaders(), "Content-Type": "application/json" },
    body: JSON.stringify(body || {}),
  });
  if (r.status === 401) {
    window.location.href = "/login";
    throw new Error("Unauthorized");
  }
  const j = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(j?.error?.message || j?.error || "Request failed");
  return j;
}

async function apiDelete(url) {
  const r = await fetch(url, {
    method: "DELETE",
    credentials: "include",
    headers: authHeaders(),
  });
  if (r.status === 401) {
    window.location.href = "/login";
    throw new Error("Unauthorized");
  }
  // 204 → pas de body
  if (!r.ok && r.status !== 204) {
    const j = await r.json().catch(() => ({}));
    throw new Error(j?.error?.message || j?.error || "Request failed");
  }
  return true;
}

/* =========================
   Current user via /get-user (obligatoire)
   ========================= */
async function getCurrentUser() {
  // Ex: {"id":4,"fullname":"...","username":"gKirira", ...}
  return apiGet("/get-user");
}

function safeClosePopup() {
  try {
    closePopup();
  } catch (_) {}
}

/* =========================
   UI Vendors + toggles (REST)
   ========================= */
function displayVendors(vendorData, selectedOptions) {
  const vendorListContainer = document.getElementById("vendor-list");
  const selectedOptionIds = selectedOptions
    ? selectedOptions.map((o) => o.vendor_shipping_option_id)
    : [];

  const priorityNames = ["Me", "Softadastra Logistic", "Adastra Logistic"];
  const sortedVendors = vendorData.results.sort((a, b) => {
    const ap = priorityNames.includes(a.name)
      ? priorityNames.indexOf(a.name)
      : Infinity;
    const bp = priorityNames.includes(b.name)
      ? priorityNames.indexOf(b.name)
      : Infinity;
    return ap - bp;
  });

  function attachToggleBehavior(sliderInput, vendorId, hiddenInput, labelEl) {
    let inflight = false;

    async function applyChange(checked) {
      if (inflight) return;
      inflight = true;

      // Optimistic UI + modal “loading”
      sliderInput.disabled = true;
      labelEl?.classList.add("is-saving");
      showMessage("loading", {
        text: checked
          ? "Enregistrement de l’option…"
          : "Désactivation de l’option…",
        closeOnBackdrop: true,
        autoCloseMs: 0,
        lockScroll: false,
        showBackdrop: false,
      });

      try {
        if (checked) {
          await apiPut(`/api/v1/me/send-options/${vendorId}`, { active: true });
        } else {
          await apiDelete(`/api/v1/me/send-options/${vendorId}`);
          // (ou) await apiPut(`/api/v1/me/send-options/${vendorId}`, { active:false });
        }

        // ✅ d’abord on ferme le "loading" (évite double-lock)
        safeClosePopup();

        // ✅ puis on affiche un succès court (un seul lock temporaire)
        showMessage("success", {
          text: "Préférence enregistrée.",
          autoCloseMs: 1200,
        });
      } catch (err) {
        // rollback UI
        sliderInput.checked = !checked;
        hiddenInput.value = sliderInput.checked ? "on" : "off";

        // ✅ ferme le "loading" avant d’afficher l’erreur
        safeClosePopup();

        showMessage("error", {
          text: err?.message || "Impossible de mettre à jour la préférence.",
          closeOnBackdrop: true,
        });
        console.error("Send-option toggle failed:", err);
      } finally {
        sliderInput.disabled = false;
        labelEl?.classList.remove("is-saving");
        inflight = false;
      }
    }

    const span = sliderInput.nextElementSibling; // .slider
    if (span) {
      span.addEventListener("click", (e) => {
        e.preventDefault();
        const targetState = !sliderInput.checked;
        sliderInput.checked = targetState;
        hiddenInput.value = targetState ? "on" : "off";
        applyChange(targetState);
      });
    }

    sliderInput.addEventListener("change", () => {
      hiddenInput.value = sliderInput.checked ? "on" : "off";
      applyChange(sliderInput.checked);
    });
  }

  vendorListContainer.innerHTML = "";

  sortedVendors.forEach((vendor) => {
    const vendorContainer = document.createElement("div");
    const hr = document.createElement("hr");
    hr.style.border = "none";
    hr.style.borderTop = "1px solid #eee";
    hr.style.margin = "20px 0";
    vendorListContainer.appendChild(hr);

    vendorContainer.classList.add("input-container", "pt-3");

    const contentContainer = document.createElement("div");
    contentContainer.classList.add("content-container");

    const box = document.createElement("div");
    box.classList.add("box");

    if (priorityNames.includes(vendor.name)) {
      box.style.border = "2px solid #ffd700";
      box.style.boxShadow = "0 0 12px rgba(255, 215, 0, 0.3)";
      box.style.borderRadius = "10px";
    }

    const flexContainer = document.createElement("div");
    flexContainer.classList.add("flex-container");

    const imageContainer = document.createElement("div");
    imageContainer.classList.add("image-container");
    const logoBox = document.createElement("div");
    logoBox.classList.add("logo-box");

    const logoImg = document.createElement("img");
    logoImg.src = vendor.logo
      ? `/public/images/agences/${vendor.logo}`
      : "/public/images/default/adastra.jpg";
    logoImg.alt = `Logo ${vendor.name}`;
    logoImg.classList.add("logo");
    logoBox.appendChild(logoImg);
    imageContainer.appendChild(logoBox);

    const textContainer = document.createElement("div");
    textContainer.classList.add("text-container");

    const label = document.createElement("label");
    label.setAttribute("for", `send-option-${vendor.id}`);
    if (vendor.name === "Me") {
      label.innerHTML = `
        <span style="color:#ff9900; font-weight:bold;">${vendor.name}</span>
        <span style="font-size:13px; background:#d9f3ff; color:#007bff; padding:2px 6px; border-radius:8px; margin-left:5px;">
          👤 You
        </span>`;
    } else if (
      vendor.name === "Softadastra Logistic" ||
      vendor.name === "Adastra Logistic"
    ) {
      label.innerHTML = `
        <span style="color:#ff9900; font-weight:bold;">${vendor.name}</span>
        <span style="font-size:13px; background:#ffe9cc; color:#b46b00; padding:2px 6px; border-radius:8px; margin-left:5px;">
          🌟 Recommended
        </span>`;
    } else {
      label.textContent = vendor.name;
    }

    const descriptionWrapper = document.createElement("div");
    descriptionWrapper.style.position = "relative";
    descriptionWrapper.style.maxHeight = "80px";
    descriptionWrapper.style.overflow = "hidden";
    descriptionWrapper.style.transition = "max-height 0.3s ease";

    const description = document.createElement("p");
    description.textContent = vendor.description || "";
    descriptionWrapper.appendChild(description);

    const toggleDescriptionBtn = document.createElement("span");
    toggleDescriptionBtn.textContent = "See more";
    toggleDescriptionBtn.className = "city-list-toggle";
    toggleDescriptionBtn.style.display = "none";

    textContainer.appendChild(label);
    textContainer.appendChild(descriptionWrapper);
    textContainer.appendChild(toggleDescriptionBtn);

    setTimeout(() => {
      if (description.scrollHeight > 90) {
        toggleDescriptionBtn.style.display = "inline-block";
        toggleDescriptionBtn.addEventListener("click", function () {
          const isExpanded = descriptionWrapper.style.maxHeight !== "80px";
          descriptionWrapper.style.maxHeight = isExpanded ? "80px" : "500px";
          toggleDescriptionBtn.textContent = isExpanded
            ? "See more"
            : "See less";
        });
      }
    }, 0);

    if (vendor.destinations && vendor.destinations.length > 0) {
      const destinationTitle = document.createElement("small");
      destinationTitle.textContent = "Cities served:";
      destinationTitle.classList.add("dest-title");
      textContainer.appendChild(destinationTitle);

      const cityList = document.createElement("ul");
      cityList.classList.add("city-list");
      vendor.destinations.forEach((d) => {
        const li = document.createElement("li");
        li.textContent = `📍 ${d.city}, ${d.country}`;
        cityList.appendChild(li);
      });
      textContainer.appendChild(cityList);

      if (vendor.destinations.length > 5) {
        const toggleBtn = document.createElement("span");
        toggleBtn.textContent = "See more";
        toggleBtn.className = "city-list-toggle";
        toggleBtn.addEventListener("click", function () {
          cityList.classList.toggle("expanded");
          toggleBtn.textContent = cityList.classList.contains("expanded")
            ? "See less"
            : "See more";
        });
        textContainer.appendChild(toggleBtn);
      }
    }

    flexContainer.appendChild(imageContainer);
    flexContainer.appendChild(textContainer);
    box.appendChild(flexContainer);
    contentContainer.appendChild(box);

    // ==== Switch ====
    const sliderInput = document.createElement("input");
    sliderInput.type = "checkbox";
    sliderInput.id = `send-option-${vendor.id}`;
    sliderInput.name = `send_option_${vendor.id}`;
    sliderInput.value = "on";

    const hiddenInput = document.createElement("input");
    hiddenInput.type = "hidden";
    hiddenInput.name = `send_option_${vendor.id}`;
    hiddenInput.value = "off";

    if (selectedOptionIds.includes(vendor.id)) {
      sliderInput.checked = true;
      hiddenInput.value = "on";
    }

    const sliderSpan = document.createElement("span");
    sliderSpan.classList.add("slider");

    contentContainer.appendChild(sliderInput);
    contentContainer.appendChild(sliderSpan);
    contentContainer.appendChild(hiddenInput);
    vendorContainer.appendChild(contentContainer);
    vendorListContainer.appendChild(vendorContainer);

    attachToggleBehavior(sliderInput, vendor.id, hiddenInput, label);
  });
}

/* =========================
   Chargement initial (vraies routes)
   ========================= */
const vendorListContainer = document.getElementById("vendor-list");
const loadingSpinner = document.getElementById("vendor-loading-spinner");

// Affiche uniquement le spinner (pas de modal)
vendorListContainer.innerHTML = "";
if (loadingSpinner) loadingSpinner.style.display = "block";

Promise.all([
  getCurrentUser(), // /get-user
  apiGet("/api/get-vendorShippingOptions"), // vendors
  apiGet("/api/v1/me/send-options"), // préférences REST
])
  .then(([me, vendorData, sendOptionsResp]) => {
    if (loadingSpinner) loadingSpinner.style.display = "none";
    // fermer au cas où un ancien modal serait resté (refresh précédent)
    safeClosePopup();

    const selectedOptions = Array.isArray(sendOptionsResp?.data)
      ? sendOptionsResp.data
      : sendOptionsResp?.send_options || [];
    displayVendors(vendorData, selectedOptions);
  })
  .catch((error) => {
    if (loadingSpinner) loadingSpinner.style.display = "none";
    console.error("Erreur lors du chargement :", error);
    // ici on utilise le modal mais on le laisse fermable par backdrop
    showMessage("error", {
      text: "Impossible de charger les options d’expédition.",
      closeOnBackdrop: true,
    });
  });

/* =========================
   (Optionnel) Form: on n’envoie plus POST legacy,
   on valide juste et on affiche une confirmation via ton modal.
   ========================= */
$(document).ready(function () {
  $("#profile-form").submit(function (e) {
    e.preventDefault();

    // vérifie qu’il y a au moins une option active côté UI
    const hasActive = !!document.querySelector(
      'input[type="checkbox"][id^="send-option-"]:checked'
    );
    if (!hasActive) {
      showMessage("error", {
        text: "You must activate at least one option before submitting the form.",
      });
      return;
    }

    // Si toutes les bascules sont déjà parties en PUT/DELETE,
    // il n’y a rien à poster : on affiche juste un succès UX.
    showMessage("success", { text: "Shipping options updated successfully!" });
  });
});
