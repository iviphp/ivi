// ==== Bases & helpers (reprend EXACTEMENT ce qu’on a dans le <head>) ====
const PHP_BASE =
  window.SA_PHP_BASE ||
  document.querySelector('meta[name="sa-php-base"]')?.content ||
  location.origin ||
  "https://softadastra.com";

const NODE_BASE =
  window.SA_NODE_BASE ||
  document.querySelector('meta[name="sa-node-base"]')?.content ||
  "https://api.softadastra.com";

// helpers d’URL sûrs
const phpUrl = (p = "") =>
  PHP_BASE.replace(/\/+$/, "") + "/" + String(p).replace(/^\/+/, "");
const nodeUrl = (p = "") =>
  NODE_BASE.replace(/\/+$/, "") + "/" + String(p).replace(/^\/+/, "");

// centralise les endpoints réutilisés
window.ENDPOINTS = {
  ...(window.ENDPOINTS || {}),
  php: {
    ...(window.ENDPOINTS?.php || {}),
    getUser:
      document.querySelector('meta[name="sa-get-user"]')?.content ||
      "/get-user",
    ordersMetrics: "/orders/metrics",
    sellerItems: (uid) => `/api/seller/items/${encodeURIComponent(uid)}`,
    userReviewsStats: (uid) =>
      `/api/user/${encodeURIComponent(uid)}/reviews/stats`,
    userReviews: (uid) => `/api/user/${encodeURIComponent(uid)}/reviews`,
    updatePhoto: "/user/update-photo",
    updateCover: "/user/update-cover",
    getFlash: "/api/get-flash",
    login: "/login",
    googleUrl: "/google-login-url",
    csrf: "/get-csrf-token",
  },
  node: {
    ...(window.ENDPOINTS?.node || {}),
    likesBatch: (idsCsv) =>
      `/api/products/likes?ids=${encodeURIComponent(idsCsv)}`,
  },
};

const ENDPOINTS_D = window.ENDPOINTS;

function redirectToPage(type, userId) {
  const url = phpUrl(`/follow-list/${userId}?type=${encodeURIComponent(type)}`);
  window.location.href = url;
}

// --- helpers réutilisables (une seule fois dans le fichier) ---
async function toJSONSafe(res) {
  const ct = (res.headers.get("content-type") || "").toLowerCase();
  const txt = await res.text();
  if (!ct.includes("application/json")) {
    throw new Error(`Réponse non-JSON (${res.status}) : ${txt.slice(0, 120)}…`);
  }
  try {
    return JSON.parse(txt);
  } catch {
    throw new Error("JSON invalide");
  }
}

function userEndpointPath() {
  const ep = window.ENDPOINTS?.php?.getUser;
  if (typeof ep === "function") return ep();
  if (typeof ep === "string" && ep.trim()) return ep;
  // fallback: <meta> ou chemin par défaut
  const meta = document.querySelector('meta[name="sa-get-user"]')?.content;
  return meta || "/get-user";
}

// petites aides DOM tolérantes
function setText(id, value = "") {
  const el = document.getElementById(id);
  if (el) el.textContent = String(value ?? "");
}
function setDataAttr(id, name, value = "") {
  const el = document.getElementById(id);
  if (el) el.dataset[name] = String(value ?? "");
}
function setSrc(id, src, fallback = "") {
  const el = document.getElementById(id);
  if (el && src) el.src = src;
  else if (el && fallback) el.src = fallback;
}

// --- helper URL sellerItems tolérant (fn, string avec :id, string simple)
function sellerItemsPath(userId) {
  const ep = window.ENDPOINTS?.php?.sellerItems;
  if (typeof ep === "function") return ep(userId);
  if (typeof ep === "string" && ep.includes(":id")) {
    return ep.replace(":id", encodeURIComponent(userId));
  }
  if (typeof ep === "string" && ep.trim()) {
    // Si on a juste "/api/seller/items" → on ajoute l'id
    const base = ep.replace(/\/+$/, "");
    return `${base}/${encodeURIComponent(userId)}`;
  }
  // fallback par défaut
  return `/api/seller/items/${encodeURIComponent(userId)}`;
}
async function checkIfFollowing(userId) {
  try {
    const response = await fetch(`/is-following/${userId}`);
    if (!response.ok) {
      throw new Error("Error while retrieving the follow status");
    }
    const data = await response.json();
    return data.following;
  } catch (error) {
    console.error("Erreur:", error);
    return false;
  }
}

function copyNumber(iconElement, number) {
  const input = document.createElement("input");
  input.value = "+" + number;
  document.body.appendChild(input);
  input.select();
  document.execCommand("copy");
  document.body.removeChild(input);
  iconElement.classList.remove("fa-copy");
  iconElement.classList.add("fa-check");
  iconElement.classList.add("copied");
  const labelSpan = iconElement.nextElementSibling;
  if (labelSpan) {
    labelSpan.textContent = "Copié ✅";
  }

  const successPopup = document.getElementById("success-popup");
  const successMessage = document.getElementById("success-message");
  successMessage.textContent = "Copié de +" + number;
  successPopup.style.display = "block";

  const rect = iconElement.getBoundingClientRect();
  successPopup.style.top = `${rect.top - 30}px`;
  successPopup.style.left = `${rect.left}px`;

  setTimeout(() => {
    iconElement.classList.remove("fa-check");
    iconElement.classList.add("fa-copy");
    iconElement.classList.remove("copied");

    if (labelSpan) {
      labelSpan.textContent = "Copier";
    }
  }, 2000);

  setTimeout(() => {
    successPopup.style.display = "none";
  }, 3000);
}

(() => {
  // ====== Helpers DOM ======
  const $ = (sel) => document.querySelector(sel);
  const setText = (sel, v) => {
    const n = document.querySelector(sel);
    if (n) n.textContent = v;
  };

  const setSrc = (sel, v) => {
    const n = $(sel);
    if (n) n.src = v;
  };

  // Éléments attendus dans la page
  const el = {
    cover: $("#cover_image_preview"),
    avatar: $("#profile_image_preview"),
    name: $("#user-fullname"),
    country: $("#country-image"),
    city: $("#user-city"),
    phone: $("#phone-text"),
    phoneBtn: document.querySelector("#user-phone i"),
    followers: $("#followers-count"),
    following: $("#following-count"),
    followersStat: $("#followers-stat"),
    followingStat: $("#following-stat"),
    msgBtn: $("#messageBtn"),
    subBtn: $("#subscribeBtn"),
  };

  // UI: états du bouton follow
  const setFollowBtn = (isFollowing, { loading = false } = {}) => {
    if (!el.subBtn) return;
    el.subBtn.disabled = loading;
    el.subBtn.classList.toggle("is-loading", loading);
    el.subBtn.classList.toggle("subscribed", isFollowing && !loading);
    el.subBtn.classList.toggle("subscribe", !isFollowing && !loading);
    el.subBtn.textContent = loading
      ? isFollowing
        ? "Désabonnement…"
        : "Abonnement…"
      : isFollowing
      ? "Se désabonner"
      : "S'abonner";
  };

  // State local
  let profile = null;
  let isFollowing = false;
  let inflight = false; // anti double-click
  let authed = true;

  // ========= API calls =========
  const json = (r) =>
    r.ok
      ? r.json()
      : r
          .json()
          .catch(() => ({}))
          .then((j) => {
            throw { status: r.status, data: j };
          });

  const getSlug = () => {
    const parts = window.location.pathname.replace(/\/+$/, "").split("/");
    const last = parts.pop() || parts.pop();
    return (last || "").replace(/^@/, ""); // retire le @ si présent
  };

  async function fetchProfile(slug) {
    const clean = encodeURIComponent(slug.replace(/^@/, ""));
    const r = await fetch(`/api/profile/${clean}`, { credentials: "include" });
    if (!r.ok) {
      // 404 → profil introuvable (pas 401)
      throw new Error(
        r.status === 404 ? "Profil introuvable" : "Erreur profil"
      );
    }
    const data = await r.json();
    if (!data || data.error)
      throw new Error(data?.error || "Profil introuvable");
    return data;
  }

  async function checkFollowing(userId) {
    const r = await fetch(`/is-following/${userId}`, {
      credentials: "include",
    });
    if (r.status === 401) {
      authed = false;
      return false;
    }
    const j = await r.json().catch(() => ({}));
    return !!j.following;
  }

  async function followRequest(userId, follow) {
    const url = follow ? "/subscribe" : "/unsubscribe";
    const r = await fetch(url, {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ user_id: userId }),
    });
    if (r.status === 401) {
      authed = false;
      throw { status: 401 };
    }
    const j = await r.json().catch(() => ({}));
    if (!r.ok || !j.success) throw { status: r.status, data: j };
    return j; // j peut contenir followers_count mis à jour
  }

  // ========= Wiring =========
  function wireStatics(data) {
    // Images & texte
    setSrc(
      "#cover_image_preview",
      `/public/images/cover/${data.cover_photo || "default.jpg"}`
    );
    setSrc(
      "#profile_image_preview",
      data.photo || "/public/images/avatar/default.png"
    );
    setText("#user-fullname", data.fullname || "—");

    const countryImg = data.country_image
      ? `/public/images/pays/${data.country_image}`
      : "/public/images/pays/kampala.jpg";
    setSrc("#country-image", countryImg);
    setText("#user-city", data.city || "Kampala");

    const cleaned = String(data.phone || "")
      .replace(/\s+/g, "")
      .replace(/^\+?/, "");
    setText("#phone-text", cleaned ? `+${cleaned}` : "—");
    if (el.phoneBtn && cleaned) {
      el.phoneBtn.onclick = () => copyNumber(el.phoneBtn, cleaned);
    }

    // Compteurs
    setText("#followers-count", Number(data.followers_count || 0));
    setText("#following-count", Number(data.following_count || 0));

    // Redirections pro (liste followers/following)
    if (el.followersStat)
      el.followersStat.onclick = () => {
        window.location.href = `/follow-list/${data.id}?type=followers`;
      };
    if (el.followingStat)
      el.followingStat.onclick = () => {
        window.location.href = `/follow-list/${data.id}?type=following`;
      };

    // Message
    if (el.msgBtn && data.id)
      el.msgBtn.onclick = () => {
        window.location.href = `/chat/conversation/${data.id}`;
      };
  }

  async function init() {
    try {
      const slug = getSlug();
      profile = await fetchProfile(slug);
      wireStatics(profile);

      isFollowing = await checkFollowing(profile.id);
      setFollowBtn(isFollowing);

      if (el.subBtn) {
        el.subBtn.onclick = async () => {
          if (!authed) {
            window.location.href = "/login";
            return;
          }
          if (inflight) return;

          // Interdit de se suivre soi-même
          const meId = Number(localStorage.getItem("sa_user_id") || 0);
          if (meId && profile && Number(profile.id) === meId) return;

          inflight = true;
          // Optimistic UI
          const wasFollowing = isFollowing;
          const followersEl = el.followers;
          const beforeCount = Number(followersEl?.textContent || 0);

          isFollowing = !wasFollowing;
          setFollowBtn(isFollowing, { loading: true });
          if (followersEl)
            followersEl.textContent = String(
              Math.max(0, beforeCount + (isFollowing ? 1 : -1))
            );

          try {
            const res = await followRequest(profile.id, !wasFollowing);
            // si le backend renvoie le count exact, on l’applique
            if (res.followers_count !== undefined && followersEl) {
              followersEl.textContent = String(res.followers_count);
            }
          } catch (e) {
            // rollback en cas d’erreur
            isFollowing = wasFollowing;
            if (followersEl) followersEl.textContent = String(beforeCount);
            if (e?.status === 401) {
              window.location.href = "/login";
              return;
            }
            alert(e?.data?.error || "Erreur réseau. Réessayez.");
            console.error("follow/unfollow failed:", e);
          } finally {
            inflight = false;
            setFollowBtn(isFollowing, { loading: false });
          }
        };
      }
    } catch (e) {
      console.error(e);
      setText("#user-fullname", "User not found");
    }
  }

  // Copier téléphone
  window.copyNumber = async (_icon, number) => {
    try {
      await navigator.clipboard.writeText("+" + number);
      // petit feedback visuel
      _icon.classList.add("copied");
      setTimeout(() => _icon.classList.remove("copied"), 1200);
    } catch {}
  };

  // Go
  document.addEventListener("DOMContentLoaded", init);
})();

// document.addEventListener("DOMContentLoaded", fetchUserData);

let currentPage = 1;
const articlesPerPage = 8;
let articles = [];
let userId = null;

let totalArticles = 0;
let totalViews = 0;

function fetchUserId() {
  const parts = window.location.pathname.split("/");
  const raw = parts[parts.length - 1] || parts[parts.length - 2];
  const slug = (raw || "").replace(/^@/, ""); // retire @
  fetch(`/api/profile/${encodeURIComponent(slug)}`)
    .then((res) => {
      if (res.status === 404) throw new Error("User not found");
      return res.json();
    })
    .then((data) => {
      if (data && data.id) {
        userId = data.id;
        fetchProductStats();
      } else {
        throw new Error("User data not found");
      }
    })
    .catch((err) => {
      console.error("Error fetching user data:", err);
      const msg = document.getElementById("no-articles-message");
      if (msg) {
        msg.textContent = "User not found.";
        msg.style.display = "block";
      }
    });
}

function truncateDescription(description) {
  return description.length > 40
    ? description.substring(0, 40) + "..."
    : description;
}

function fetchProductStats() {
  if (userId === null) {
    console.error("ID utilisateur non trouvé");
    return;
  }
  fetch(`/api/seller/items/${userId}`)
    .then((response) => {
      if (response.status === 404) {
        totalArticles = 0;
        totalViews = 0;
        return null;
      }
      if (!response.ok) {
        throw new Error("Failed to retrieve articles");
      }
      return response.json();
    })
    .then((data) => {
      if (data && data.length > 0) {
        totalArticles = data.length;
        totalViews = data.reduce((sum, article) => sum + article.views, 0);
      }
      document.getElementById("articles-count").textContent = totalArticles;
      document.getElementById("total-views").textContent = totalViews;
    })
    .catch((error) => {
      console.error("Error retrieving articles:", error);
      document.getElementById("articles-count").textContent = "Error";
      document.getElementById("total-views").textContent = "Error";
    });
}

document.addEventListener("DOMContentLoaded", () => {
  fetchUserId();

  const pagination = document.getElementById("pagination");
  if (pagination) {
    pagination.addEventListener("click", function (event) {
      if (
        event.target.classList.contains("nav-item") &&
        !event.target.classList.contains("disabled")
      ) {
        event.preventDefault();
        const page = parseInt(event.target.getAttribute("data-page"));
        currentPage = page;
        fetchUserId();
      }
    });
  }
});

document
  .getElementById("profile_image_preview")
  .addEventListener("click", function () {
    const popup = document.getElementById("image-popup");
    const popupImg = document.getElementById("popup-img");

    const zoomSrc = this.dataset.zoomSrc || this.src;
    popupImg.src = zoomSrc;
    popup.classList.add("show");
  });

document.querySelector(".close-popup").addEventListener("click", function () {
  document.getElementById("image-popup").classList.remove("show");
});

document.getElementById("image-popup").addEventListener("click", function (e) {
  if (e.target.id === "image-popup") {
    this.classList.remove("show");
  }
});

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
(() => {
  // ---- Tabs internes -> renderers (pas d'URL) ----
  const ROUTES = {
    listings: renderListings,
    reviews: renderReviews,
    favorites: renderFavorites,
    about: renderAbout,
    proshop: renderProShop,
  };

  const DEFAULT_TAB = "listings";
  const PROFILE_USER_ID = window.SA_PROFILE_USER_ID || null;
  const API_BASE = window.SA_API_BASE_PHP || ""; // ex: "https://softadastra.com"
  const NODE_API = NODE_BASE;

  // ==== Refs DOM ====
  const view = document.getElementById("account-view");
  const navLinks = Array.from(
    document.querySelectorAll(".account-bottom-nav__item")
  );
  updateFavoritesBadge();
  updateReviewsBadgeBoot();

  // ==== Helpers ====
  function setActive(tab) {
    navLinks.forEach((a) => {
      const isActive = a.dataset.tab === tab;
      a.classList.toggle("active", isActive);
      if (isActive) a.setAttribute("aria-current", "page");
      else a.removeAttribute("aria-current");
    });
  }

  function showSkeleton(count = 6) {
    view.innerHTML = `<div class="spa-skel">${Array.from({ length: count })
      .map(() => "<div></div>")
      .join("")}</div>`;
  }

  // ==== Navigation ====
  let navToken = 0;

  async function navigate(tab, { smooth = false, scroll = false } = {}) {
    const render = ROUTES[tab] || ROUTES[DEFAULT_TAB];
    const myToken = ++navToken;

    view.dataset.tab = tab;
    setActive(tab); // <-- IMPORTANT: on met à jour l’état visuel du nav

    showSkeleton(tab === "about" ? 2 : 6);

    await Promise.resolve();

    try {
      await render({
        token: myToken,
      });
      // ici tu peux gérer scroll si besoin
      // if (scroll) { ... }
    } catch (e) {
      if (myToken !== navToken) return; // navigation déjà changée
      console.error(e);
      view.innerHTML = `<div class="about-box">Une erreur est survenue. Réessaie.</div>`;
    }
  }

  // Permet aux renderers de vérifier s’ils sont périmés
  function isStale(token) {
    return token !== navToken;
  }

  // ==== Listeners nav ====
  navLinks.forEach((a) => {
    a.addEventListener("click", (ev) => {
      if (ev.ctrlKey || ev.metaKey || ev.shiftKey) return;
      ev.preventDefault();
      navigate(a.dataset.tab, {
        scroll: false,
        smooth: false,
      });
    });
  });

  function chunkRenderInto(
    targetEl,
    htmlArray,
    { chunk = 12, delay = 16, gridClass = "listings-grid" } = {}
  ) {
    // Si on te donne déjà la grille (ex: #grid-listings), on la vide.
    // Sinon, on en crée une et on l’ajoute à targetEl.
    let grid = targetEl;
    if (!grid || !grid.classList || !grid.classList.contains(gridClass)) {
      grid = document.createElement("div");
      grid.className = gridClass;
      targetEl.appendChild(grid);
    } else {
      grid.innerHTML = "";
    }

    // Planificateur cross-browser : rIC avec {timeout}, sinon setTimeout
    const schedule = (cb) => {
      if ("requestIdleCallback" in window) {
        return window.requestIdleCallback(cb, {
          timeout: delay,
        });
      }
      return setTimeout(cb, delay);
    };

    let i = 0;

    function tick() {
      const frag = document.createDocumentFragment();
      for (let c = 0; c < chunk && i < htmlArray.length; c++, i++) {
        const wrap = document.createElement("div");
        wrap.innerHTML = htmlArray[i];
        frag.appendChild(wrap.firstElementChild);
      }
      grid.appendChild(frag);

      if (i < htmlArray.length) {
        schedule(tick);
      }
    }
    tick();
  }

  /* ====== CONFIG / HELPERS API ====== */

  function pickImage(p) {
    return (
      (p.images && p.images[0]) ||
      p.image_url ||
      "/public/images/default/adastra.jpg"
    );
  }

  function formatPrice(p) {
    if (p.formatted_price) return p.formatted_price; // "700.00 USD"
    const price = p.price ?? p.converted_price ?? "";
    const curr = (p.currency || "").toUpperCase();
    return [price, curr].filter(Boolean).join(" ").trim();
  }

  // ----- Prix: USD / UGX / CDF + drapeau -----
  function formatPriceBlock(a) {
    const curr = String(a.currency || "").toUpperCase();

    const flagFor = (c) => (c === "UGX" ? "🇺🇬" : c === "CDF" ? "🇨🇩" : "");

    // formatters
    const nfGroup0 = new Intl.NumberFormat("en", {
      maximumFractionDigits: 0,
    });
    const nfCompact = new Intl.NumberFormat("en", {
      notation: "compact",
      compactDisplay: "short",
      maximumFractionDigits: 1,
    });

    // parse helper
    const num = (v) => {
      if (typeof v === "number") return v;
      if (!v) return 0;
      const parsed = parseFloat(String(v).replace(/[^0-9.-]+/g, ""));
      return Number.isFinite(parsed) ? parsed : 0;
    };

    // --- montant local fiable ---
    let localAmount = 0;
    if (curr === "USD") {
      localAmount = num(a.price); // déjà en USD
    } else if (curr === "UGX" || curr === "CDF") {
      // lire depuis formatted/original (montants locaux)
      localAmount = a.formatted_price
        ? num(a.formatted_price)
        : num(a.original_price);
      if (!localAmount) localAmount = num(a.price); // fallback
    } else {
      localAmount = a.formatted_price ? num(a.formatted_price) : num(a.price);
    }

    // --- rendu texte local ---
    let localText = "";
    if (curr === "USD") {
      localText = `$ ${localAmount.toFixed(2)}`;
    } else if (curr === "UGX") {
      const compact = nfCompact
        .format(localAmount)
        .replace(/K\b/i, "k")
        .replace(/M\b/i, "m")
        .replace(/B\b/i, "b");
      localText = `${compact} ${curr}`;
    } else if (curr === "CDF") {
      const compactNeeded = localAmount >= 100000;
      const base = compactNeeded
        ? nfCompact
            .format(localAmount)
            .replace(/K\b/i, "k")
            .replace(/M\b/i, "m")
            .replace(/B\b/i, "b")
        : nfGroup0.format(localAmount);
      localText = `${base} ${curr}`;
    } else {
      localText = `${nfGroup0.format(localAmount)} ${curr}`;
    }

    // tooltip (montant complet)
    const titleFull = `${nfGroup0.format(localAmount)} ${curr}`;

    // équivalent USD si UGX/CDF
    let usdLine = "";
    if (curr === "UGX" || curr === "CDF") {
      const usd = num(a.converted_price);
      if (usd > 0)
        usdLine = `<span class="sa-card__usd">≈ $ ${usd.toFixed(2)}</span>`;
    }

    return `
        <div class="sa-card__pricebox">
          <p class="sa-card__price" title="${titleFull}">
            ${
              curr === "USD"
                ? ""
                : `<span class="sa-flag">${flagFor(curr)}</span>`
            }
            ${localText}
          </p>
          ${usdLine}
        </div>
    `;
  }

  function starsFromAverage(avg) {
    if (avg == null) return 0;
    const n = Math.round(Number(avg));
    return Math.max(0, Math.min(5, n));
  }

  function badgeFrom(p) {
    if ((p.condition_name || "").toLowerCase().includes("new")) return "NEW";
    if (Number(p.boost) > 0) return "BOOST";
    return "";
  }

  async function fetchUserProducts(userId, { limit = 5, offset = 0 } = {}) {
    // Query params propres
    const qp = new URLSearchParams();
    if (Number.isFinite(limit) && limit > 0) qp.set("limit", String(limit));
    if (Number.isFinite(offset) && offset > 0) qp.set("offset", String(offset));

    // URL sûre via ENDPOINTS + phpUrl()
    const path = sellerItemsPath(userId); // ex: "/api/seller/items/123"
    const url = phpUrl(path) + (qp.toString() ? `?${qp}` : "");

    // Requête avec cookies
    const res = await fetch(url, { credentials: "include", cache: "no-store" });

    // 404 = pas d’articles pour ce vendeur
    if (res.status === 404) return [];

    if (!res.ok) {
      // lève une erreur claire (sera catchée en amont si besoin)
      throw new Error(`GET ${url} → ${res.status} ${res.statusText}`);
    }

    // Lecture JSON robuste (évite les "JSON.parse unexpected character…")
    const arr = await toJSONSafe(res);

    // Normalisation des objets produits
    const normalized = (Array.isArray(arr) ? arr : []).map((p) => {
      const pid = normId(p.id ?? p.product_id);
      const avg = Number(p.average_rating);
      const stars = Number.isFinite(avg)
        ? Math.round(avg)
        : Math.round(Number(p.average_rating ?? 0));

      return {
        id: pid,
        title: p.title,
        image: pickImage(p),
        price: p.price,
        original_price: p.original_price,
        formatted_price: p.formatted_price,
        currency: p.currency,
        converted_price: p.converted_price,
        avg: Number.isFinite(avg) ? Math.max(0, Math.min(5, avg)) : null,
        stars: Math.max(0, Math.min(5, stars)),
        sold: p.review_count || 0,
        city: p.city_name || "",
        shop: p.seller_fullname || "Softadastra",
        badge: (p.condition_name || "").toLowerCase().includes("new")
          ? "NEW"
          : Number(p.boost) > 0
          ? "BOOST"
          : "",
        status: (p.status || "").toLowerCase(),
        boost: Number(p.boost) || 0,
        views: Number(p.views) || 0,
        quantity: Number(p.quantity) || 0,
        sizes: Array.isArray(p.sizes) ? p.sizes : [],
        colors: Array.isArray(p.colors) ? p.colors : [],
        brand_name: p.brand_name || "",
        condition_name: p.condition_name || "",
        category_name: p.category_name || "",
      };
    });

    return normalized;
  }

  function renderStarsFromAvg(avg = 0) {
    const a = Math.max(0, Math.min(5, Number(avg) || 0));
    const full = Math.floor(a);
    const hasHalf = a - full >= 0.25 && a - full < 0.75; // demi entre 0.25 et 0.74
    const rest = 5 - full - (hasHalf ? 1 : 0);

    const svg = (cls) => `
    <svg class="star ${cls}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.62L12 2 9.19 8.62 2 9.24l5.46 4.73L5.82 21z"></path>
    </svg>`;

    return `<span class="stars" aria-label="${a.toFixed(1)}/5">
    ${Array.from({ length: full })
      .map(() => svg("star--on"))
      .join("")}
    ${hasHalf ? svg("star--half") : ""}
    ${Array.from({ length: rest })
      .map(() => svg("star--off"))
      .join("")}
  </span>`;
  }

  /* ================================== */
  /* ===== REPLACE ENTIRE renderListings() ===== */
  async function renderListings() {
    // ---- Shell UI
    view.innerHTML = `
    <section>
      <h3 style="margin:6px 0 10px;">Products</h3>
      <div id="grid-listings" class="listings-grid"></div>
      <div id="first-cta" style="text-align:center;margin:12px 0;">
       <a id="see-more" class="sa-btn sa-btn--primary see-more-btn" href="#" role="button">See more</a>
      </div>
      <div id="loader" style="text-align:center;padding:12px;display:none;">Loading…</div>
    </section>
  `;

    const grid = view.querySelector("#grid-listings");
    const loader = view.querySelector("#loader");
    const ctaBox = view.querySelector("#first-cta");
    const ctaBtn = view.querySelector("#see-more");

    // ---- Config pagination
    const LIMIT_FIRST = 5; // first render
    const LIMIT_NEXT = 10; // next batches
    let nextOffset = 0;

    // ---- State
    let startedScroll = false;
    let isLoading = false;
    let hasMore = true;
    const seen = new Set();
    let fallbackAll = null; // server returns full list
    let serverIgnoresLimit = false;

    // Empty state (no products)
    function emptyStateHTML() {
      return `
      <div class="listings-empty" role="status" aria-live="polite"
           style="text-align:center;padding:24px 8px;border:1px dashed #e7e9ee;border-radius:12px;">
        <svg viewBox="0 0 24 24" width="56" height="56" aria-hidden="true"
             style="display:block;margin:0 auto 10px;color:#9aa1ac;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round;">
          <!-- Box / package -->
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
          <path d="M3.3 7.3 12 12l8.7-4.7M12 22V12"></path>
        </svg>
        <div style="font-weight:700;color:#0b0c0f;margin-bottom:4px;">No products yet</div>
        <div style="color:#667085;font-size:.95rem;">This seller hasn’t published any products yet.</div>
      </div>
    `;
    }

    // Util: append list of HTML strings to grid
    function appendCardsHTML(htmlList) {
      const frag = document.createDocumentFragment();
      for (const html of htmlList) {
        const wrap = document.createElement("div");
        wrap.innerHTML = html;
        if (wrap.firstElementChild) frag.appendChild(wrap.firstElementChild);
      }
      grid.appendChild(frag);
    }

    // Card factory
    function cardHTML(it) {
      const nextStatus = it.status === "active" ? "inactive" : "active";
      const priceBlock = formatPriceBlock(it);
      return `
                <article class="card" data-id="${
                  it.id
                }" style="content-visibility:auto;contain-intrinsic-size:300px 400px;">
                <a href="/item/${
                  it.id
                }" class="card-link" style="text-decoration:none;color:inherit;">
                    <div class="media">
                    <img loading="lazy" decoding="async"
                        src="${it.image}"
                        srcset="${it.image}?w=300 300w, ${
        it.image
      }?w=600 600w, ${it.image}?w=900 900w"
                        sizes="(max-width:380px) 92vw, (max-width:600px) 45vw, (max-width:1024px) 30vw, 220px"
                        alt="${it.title}">
                    ${it.badge ? `<span class="badge">${it.badge}</span>` : ""}
                    <div class="dropdown-menu" id="menu-${
                      it.id
                    }" role="menu" aria-hidden="true">
                        <a class="dropdown-item" href="/article/${
                          it.id
                        }">View</a>
                        <button class="dropdown-item js-publish" data-id="${
                          it.id
                        }" data-next="${nextStatus}">
                        ${it.status === "inactive" ? "Publish" : "Unpublish"}
                        </button>
                        <button class="dropdown-item js-delete" data-id="${
                          it.id
                        }">Delete</button>
                    </div>
                    </div>
                    <div class="card-body">
                    <h4 class="title clamp-2" title="${it.title}">${
        it.title
      }</h4>
                    <div class="price-block">${priceBlock}</div>
                    <div class="rating" title="${
                      it.sold
                    } reviews, rating ${(Number.isFinite(it.avg)
        ? it.avg
        : 0
      ).toFixed(1)}/5">
                        ${renderStarsFromAvg(
                          Number.isFinite(it.avg) ? it.avg : it.stars
                        )}
                        <span class="note">${
                          Number.isFinite(it.avg)
                            ? it.avg.toFixed(1)
                            : (it.stars || 0).toFixed(1)
                        }</span>
                        <span class="count">(${it.sold})</span>
                    </div>
                    <div class="chips">
                        <span class="chip" title="Quantity">Qty: ${
                          it.quantity
                        }</span>
                        <span class="chip" title="Views">👁 ${it.views}</span>
                        ${
                          it.city
                            ? `<span class="chip" title="City">${it.city}</span>`
                            : ``
                        }
                        ${
                          it.status === "inactive"
                            ? `<span class="chip chip--warn">Unpublish</span>`
                            : ``
                        }
                    </div>
                    </div>
                </a>
                </article>`;
    }

    // Take up to `limit` unseen items
    function takeUnseenMax(items, limit) {
      const out = [];
      for (const it of items) {
        if (!it || seen.has(it.id)) continue;
        seen.add(it.id);
        out.push(it);
        if (out.length >= limit) break;
      }
      return out;
    }

    // Load a batch (server or fallback)
    async function loadBatch({ limit, offset }) {
      if (serverIgnoresLimit && Array.isArray(fallbackAll)) {
        const slice = takeUnseenMax(fallbackAll.slice(offset), limit);
        if (
          offset + slice.length >= fallbackAll.length ||
          slice.length < limit
        ) {
          hasMore = false;
        }
        return slice;
      }

      const serverItems = await fetchUserProducts(PROFILE_USER_ID, {
        limit,
        offset,
      });

      // First call: detect if server ignores limit
      if (offset === 0 && serverItems && serverItems.length > limit) {
        serverIgnoresLimit = true;
        fallbackAll = serverItems;
        const slice = takeUnseenMax(fallbackAll, limit);
        if (fallbackAll.length <= limit) hasMore = false;
        return slice;
      }

      const freshClamped = takeUnseenMax(serverItems || [], limit);

      if (
        !serverItems ||
        serverItems.length < limit ||
        freshClamped.length < limit
      ) {
        hasMore = false;
      }
      return freshClamped;
    }

    // Helper: show empty state OUTSIDE the grid
    function showEmptyStateProfilePublic() {
      // cacher la grille
      grid.style.display = "none";

      const markerClass = "profile-empty-public";
      if (grid.nextElementSibling?.classList?.contains(markerClass)) return;

      const wrap = document.createElement("div");
      wrap.className = markerClass;
      wrap.setAttribute("role", "status");
      wrap.setAttribute("aria-live", "polite");
      wrap.innerHTML = `
    <section class="le-wrap">
      <div class="le-card">
        <div class="le-card__icon">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16zM12 12v10M3.3 7.3 12 12l8.7-4.7" 
              fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path>
          </svg>
        </div>
        <h3 class="le-card__title">No items yet</h3>
        <p class="le-card__text">
          This seller hasn’t listed any products for sale yet. 
          Check back later or explore other verified sellers.
        </p>
        <div class="le-cta">
          <a href="/explore" class="le-btn">
            <span>Explore sellers</span>
          </a>
        </div>
      </div>
    </section>
  `;

      grid.insertAdjacentElement("afterend", wrap);
      ctaBox.style.display = "none"; // masque CTA interne s'il existe
    }

    // Helper: show error outside the grid
    function showErrorState() {
      grid.style.display = "none";
      const err = document.createElement("div");
      err.className = "listings-empty";
      err.setAttribute("role", "alert");
      err.style.textAlign = "center";
      err.style.padding = "24px 8px";
      err.style.border = "1px dashed #e7e9ee";
      err.style.borderRadius = "12px";
      err.innerHTML = `
                    <svg viewBox="0 0 24 24" width="56" height="56" aria-hidden="true"
                        style="display:block;margin:0 auto 10px;color:#9aa1ac;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round;">
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <path d="M12 9v4"></path>
                    <path d="M12 17h.01"></path>
                    </svg>
                    <div style="font-weight:700;color:#0b0c0f;margin-bottom:4px;">Unable to load products</div>
                    <div style="color:#667085;font-size:.95rem;">Please try again later.</div>`;
      if (
        !(
          grid.nextElementSibling &&
          grid.nextElementSibling.classList?.contains("listings-empty")
        )
      ) {
        grid.insertAdjacentElement("afterend", err);
      }
      ctaBox.style.display = "none";
    }

    // Append a batch to DOM
    async function loadMore(limit, offset) {
      if (isLoading || !hasMore) return;
      isLoading = true;
      loader.style.display = "block";

      try {
        const batch = await loadBatch({
          limit,
          offset,
        });

        // If nothing at all and grid is still empty -> show empty state OUTSIDE
        if ((!batch || !batch.length) && grid.children.length === 0) {
          showEmptyStateProfilePublic({
            // Optional overrides:
            // title: "Start selling today",
            // text:  "Add your first items in these trending categories:",
            // categories: ["Womenswear", "Menswear", "Tech", "Sneakers"],
            // addUrl: "/sell/new",
            // onAddClick: (url) => { openModalOrRoute(url); }
          });
          hasMore = false;
          return;
        }

        // No more results (but some already rendered) -> stop
        if (!batch.length) {
          hasMore = false;
          return;
        }

        // Ensure grid is visible when we have content
        if (grid.style.display === "none") {
          grid.style.display = ""; // revert hidden grid in case it was empty before
          // remove previous empty block if present
          if (
            grid.nextElementSibling &&
            grid.nextElementSibling.classList?.contains("listings-empty")
          ) {
            grid.nextElementSibling.remove();
          }
        }

        appendCardsHTML(batch.map(cardHTML));
        nextOffset = offset + batch.length;
      } catch (e) {
        console.error("loadMore error:", e);
        if (grid.children.length === 0) {
          showErrorState(); // inject error outside the grid
        }
        hasMore = false;
      } finally {
        isLoading = false;
        loader.style.display = "none";
      }
    }

    // Infinite scroll (after "See more")
    let observer = null;

    function startInfiniteScroll() {
      if (startedScroll) return;
      startedScroll = true;

      const sentinel = document.createElement("div");
      sentinel.id = "scroll-sentinel";
      grid.after(sentinel);

      observer = new IntersectionObserver(
        (entries) => {
          if (entries[0].isIntersecting) {
            loadMore(LIMIT_NEXT, nextOffset);
          }
        },
        {
          rootMargin: "300px",
        }
      );

      observer.observe(sentinel);
    }

    // ---- 1) First render: exactly 5 items (or empty state)
    await loadMore(LIMIT_FIRST, 0);

    // Show button only if there is more
    ctaBox.style.display = hasMore ? "block" : "none";

    // ---- 2) Click "See more" → load one batch then start infinite scroll
    ctaBtn?.addEventListener("click", async () => {
      ctaBox.style.display = "none";
      await loadMore(LIMIT_NEXT, nextOffset);
      if (hasMore) startInfiniteScroll();
    });

    // ---- Dots menus (singleton)
    requestAnimationFrame(() => initThreeDotsMenus());
  }

  // === Spinner global plein écran =========================================
  (function setupGlobalSpinner() {
    if (window.__ADAS_SPINNER_SETUP__) return;
    window.__ADAS_SPINNER_SETUP__ = true;

    // CSS (injecté une fois)
    const css = `
            #adastra-spinner{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:100000;background:rgba(255,255,255,.65);backdrop-filter:saturate(1.1) blur(1.5px)}
            #adastra-spinner.is-open{display:flex}
            #adastra-spinner .box{display:flex;flex-direction:column;align-items:center;gap:12px;padding:18px 22px;border-radius:16px;background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.15)}
            #adastra-spinner .loader{width:56px;height:56px;border-radius:50%;border:4px solid #ff9900;border-top-color:transparent;animation:adastra-spin .8s linear infinite}
            #adastra-spinner .label{font:600 14px/1.2 system-ui,Segoe UI,Roboto,Arial;color:#212121}
            @keyframes adastra-spin{to{transform:rotate(360deg)}}`;
    const st = document.createElement("style");
    st.textContent = css;
    document.head.appendChild(st);

    // DOM overlay
    const wrap = document.createElement("div");
    wrap.id = "adastra-spinner";
    wrap.setAttribute("aria-hidden", "true");
    wrap.innerHTML = `
            <div class="box" role="status" aria-live="polite" aria-label="Loading">
            <div class="loader" aria-hidden="true"></div>
            <div class="label" id="adastra-spinner-label">Updating…</div>
            </div>`;
    document.body.appendChild(wrap);

    // helpers
    window.showGlobalSpinner = function (label = "Updating…") {
      const w = document.getElementById("adastra-spinner");
      const l = document.getElementById("adastra-spinner-label");
      if (l) l.textContent = label;
      w?.classList.add("is-open");
      w?.setAttribute("aria-hidden", "false");
    };
    window.hideGlobalSpinner = function () {
      const w = document.getElementById("adastra-spinner");
      w?.classList.remove("is-open");
      w?.setAttribute("aria-hidden", "true");
    };
  })();

  // --- Ferme tous les menus ouverts
  function closeAllMenus() {
    document
      .querySelectorAll('.dropdown-menu[aria-hidden="false"]')
      .forEach((m) => {
        m.setAttribute("aria-hidden", "true");
        // 🔄 reset des styles inline pour repartir propre au prochain open
        m.style.left = "";
        m.style.right = "";
        m.style.top = "";
        m.style.bottom = "";
      });
    document
      .querySelectorAll('.dots-btn[aria-expanded="true"]')
      .forEach((b) => {
        b.setAttribute("aria-expanded", "false");
      });
  }

  // --- Singleton d'initialisation (évite les doublons au re-render)
  function initThreeDotsMenus() {
    if (window.__ADAS_THREEDOTS_INIT__) return; // déjà fait
    window.__ADAS_THREEDOTS_INIT__ = true;

    // 1) Toggle sur chaque bouton 3 points (on écoute via délégation)
    document.addEventListener("click", (e) => {
      const btn = e.target.closest(".dots-btn");
      if (!btn) return;

      e.preventDefault();
      e.stopPropagation();

      const menuId = btn.dataset.menuId;
      const menu = document.getElementById(menuId);
      if (!menu) return;

      const isOpen = menu.getAttribute("aria-hidden") === "false";
      closeAllMenus();
      if (!isOpen) {
        menu.setAttribute("aria-hidden", "false");
        btn.setAttribute("aria-expanded", "true");

        // 🔧 Positionnement anti-débordement (après ouverture)
        requestAnimationFrame(() => {
          // valeurs par défaut
          menu.style.left = "auto";
          menu.style.right = "8px";
          menu.style.top = "46px";
          menu.style.bottom = "auto";

          const rect = btn.getBoundingClientRect();
          const menuRect = menu.getBoundingClientRect();
          const vw = Math.max(
            document.documentElement.clientWidth || 0,
            window.innerWidth || 0
          );
          const vh = Math.max(
            document.documentElement.clientHeight || 0,
            window.innerHeight || 0
          );

          // ➜ si ça dépasse à droite, on aligne à gauche
          if (rect.right + menuRect.width > vw - 8) {
            menu.style.right = "auto";
            menu.style.left = "8px";
          } else {
            menu.style.left = "auto";
            menu.style.right = "8px";
          }

          // ➜ si ça dépasse en bas, on ouvre vers le haut
          const spaceBelow = vh - rect.bottom;
          if (spaceBelow < menuRect.height + 12) {
            menu.style.top = "auto";
            menu.style.bottom = "46px";
          } else {
            menu.style.bottom = "auto";
            menu.style.top = "46px";
          }
        });
      }
    });

    // 2) Clic extérieur : ferme (listener PERSISTANT, pas {once:true})
    document.addEventListener("click", (e) => {
      // Si on clique DANS un menu, on ne ferme pas
      if (e.target.closest(".dropdown-menu") || e.target.closest(".dots-btn"))
        return;
      closeAllMenus();
    });

    // 3) Échap : ferme
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeAllMenus();
    });

    // 4) Actions du menu (Publish/Unpublish/Delete) via délégation
    document.addEventListener("click", async (e) => {
      const item = e.target.closest(".dropdown-item");
      if (!item) return;

      // On ne laisse pas l'anchor parent <a class="card-link"> naviguer
      e.preventDefault();
      e.stopPropagation();

      if (item.classList.contains("js-publish")) {
        const id = item.dataset.id;
        const next = item.dataset.next; // "active" | "inactive"

        // Texte contextualisé
        const isUnpublish = next === "inactive";
        const title = isUnpublish ? "Confirm unpublish" : "Confirm publish";
        const message = isUnpublish
          ? "Are you sure you want to unpublish this item? It will be hidden from buyers."
          : "Publish this item now and make it visible to buyers?";
        const confirmText = isUnpublish ? "Unpublish" : "Publish";

        openConfirmPopup({
          title,
          message,
          confirmText,
          cancelText: "Cancel",
          onConfirm: async () => {
            // anti double-clic
            if (item.dataset.loading === "1") return;
            item.dataset.loading = "1";

            let resp;
            try {
              showGlobalSpinner(isUnpublish ? "Unpublishing…" : "Publishing…");

              resp = await togglePublish(id, next);

              applyStatusUI(id, next, resp);
              closeAllMenus();
            } catch (err) {
              console.error(err);
              hideGlobalSpinner();
              item.dataset.loading = "0";
              safeFlashError(
                err.message || "Action impossible pour le moment."
              );
              return;
            }

            hideGlobalSpinner();
            item.dataset.loading = "0";
            safeFlashSuccess(resp?.message || "Status updated.");
          },
        });

        return;
      }

      // Delete
      if (item.classList.contains("js-delete")) {
        const id = item.dataset.id;
        openDeletePopup({
          onConfirm: async () => {
            // anti double-clic
            if (item.dataset.loading === "1") return;
            item.dataset.loading = "1";

            let resp;
            try {
              showGlobalSpinner("Deleting…");
              resp = await deleteArticle(id);
              // supprime la carte du DOM
              const card = document.querySelector(`.card[data-id="${id}"]`);
              if (card) card.remove();
              closeAllMenus();
            } catch (err) {
              console.error(err);
              hideGlobalSpinner();
              item.dataset.loading = "0";
              safeFlashError(
                err.message || "Suppression impossible pour le moment."
              );
              return;
            }

            hideGlobalSpinner();
            item.dataset.loading = "0";
            safeFlashSuccess(
              resp?.message || "Your article has been successfully deleted."
            );
          },
        });

        return;
      }
    });
  }

  // --- Mise à jour UI locale après publish/unpublish
  function applyStatusUI(productId, nextStatus, resp) {
    const card = document.querySelector(`.card[data-id="${productId}"]`);
    if (!card) return;

    // Si le backend te renvoie le status réel, priorise-le
    const effective =
      resp && resp.status ? String(resp.status) : String(nextStatus);

    // Libellé & next
    const btn = card.querySelector(".dropdown-item.js-publish");
    if (btn) {
      btn.textContent = effective === "inactive" ? "Publish" : "Unpublish";
      btn.dataset.next = effective === "inactive" ? "active" : "inactive";
    }

    // Pastille "Unpublish"
    const tag = card.querySelector(".tag-depublish");
    if (effective === "inactive") {
      if (!tag) {
        const el = document.createElement("div");
        el.className = "tag-depublish";
        el.textContent = "Unpublish";
        card.querySelector(".card-body")?.appendChild(el);
      }
    } else {
      tag && tag.remove();
    }
  }

  let __csrfRefreshedAt = 0;
  const CSRF_TTL_MS = 8 * 60 * 1000; // 8 min

  function readCsrfToken() {
    return (
      document.querySelector('input[name="csrf_token"]')?.value ||
      document.querySelector('meta[name="csrf-token"]')?.content ||
      ""
    );
  }

  function setCsrfTokenInDom(token) {
    let input = document.querySelector('input[name="csrf_token"]');
    if (!input) {
      input = document.createElement("input");
      input.type = "hidden";
      input.name = "csrf_token";
      document.body.appendChild(input);
    }
    input.value = token;

    let meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) {
      meta = document.createElement("meta");
      meta.setAttribute("name", "csrf-token");
      document.head.appendChild(meta);
    }
    meta.setAttribute("content", token);
  }

  async function refreshCsrfToken() {
    const res = await fetch("/get-csrf-token", {
      method: "GET",
      credentials: "include", // ⬅️ IMPORTANT si origines ≠
      headers: {
        Accept: "text/plain",
      },
    });
    if (!res.ok) throw new Error("Cannot refresh CSRF token");
    const token = await res.text();
    setCsrfTokenInDom(token);
    __csrfRefreshedAt = Date.now();
    return token;
  }

  async function ensureFreshCsrf() {
    const token = readCsrfToken();
    const freshEnough = token && Date.now() - __csrfRefreshedAt < CSRF_TTL_MS;
    if (!freshEnough) await refreshCsrfToken();
  }

  window.addEventListener("load", () => {
    ensureFreshCsrf().catch(() => {});
  });

  async function csrfFetch(url, options = {}, injectBodyField = true) {
    await ensureFreshCsrf();

    const opts = {
      ...options,
    };
    const csrf = readCsrfToken();

    // ⬇️ pour cross-origin (ou par sécurité)
    opts.credentials = "include";

    opts.headers = {
      Accept: "application/json",
      ...(opts.headers || {}),
      "X-CSRF-Token": csrf,
    };

    if (injectBodyField && opts.body instanceof URLSearchParams) {
      if (!opts.body.has("csrf_token")) opts.body.set("csrf_token", csrf);
    }

    return fetch(url, opts);
  }

  async function togglePublish(productId, nextStatus) {
    const url = `${API_BASE}/api/item/status`;
    const body = new URLSearchParams();
    body.set("productId", String(productId));
    body.set("status", String(nextStatus));

    const res = await csrfFetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
      },
      body,
    });

    let data = {};
    try {
      data = await res.json();
    } catch {}

    if (!res.ok || data?.success === false) {
      throw new Error(
        data?.error || data?.message || `Publish toggle failed ${res.status}`
      );
    }
    return data;
  }

  let __DELETE_POPUP_OPEN = false;

  function openDeletePopup({ onConfirm }) {
    if (__DELETE_POPUP_OPEN) return;
    __DELETE_POPUP_OPEN = true;

    const popup = document.getElementById("sa-delete-popup");
    if (!popup) {
      __DELETE_POPUP_OPEN = false;
      return;
    }

    const cancelBtn = document.getElementById("sa-delete-cancel");
    const confirmBtn = document.getElementById("sa-delete-confirm");

    const closePopup = () => {
      popup.classList.remove("is-open");
      popup.setAttribute("aria-hidden", "true");
      cancelBtn.removeEventListener("click", cancelHandler);
      confirmBtn.removeEventListener("click", confirmHandler);
      __DELETE_POPUP_OPEN = false;
    };

    const cancelHandler = () => closePopup();
    const confirmHandler = () => {
      closePopup();
      onConfirm?.();
    };

    popup.classList.add("is-open");
    popup.setAttribute("aria-hidden", "false");
    cancelBtn.addEventListener("click", cancelHandler);
    confirmBtn.addEventListener("click", confirmHandler);
    popup
      .querySelector("[data-sa-close]")
      ?.addEventListener("click", closePopup, {
        once: true,
      });
  }

  let __CONFIRM_POPUP_OPEN = false;

  function openConfirmPopup({
    title = "Confirm",
    message = "Are you sure?",
    confirmText = "Confirm",
    cancelText = "Cancel",
    onConfirm = null,
    onCancel = null,
  } = {}) {
    if (__CONFIRM_POPUP_OPEN) return;
    const el = document.getElementById("sa-confirm-popup");
    if (!el) return;

    const t = document.getElementById("sa-confirm-title");
    const m = document.getElementById("sa-confirm-text");
    const cancelBtn = document.getElementById("sa-confirm-cancel");
    const okBtn = document.getElementById("sa-confirm-ok");

    t.textContent = title;
    m.textContent = message;
    cancelBtn.textContent = cancelText;
    okBtn.textContent = confirmText;

    const close = (cb) => {
      el.classList.remove("is-open");
      el.setAttribute("inert", "");
      __CONFIRM_POPUP_OPEN = false;
      cancelBtn.removeEventListener("click", onCancelClick);
      okBtn.removeEventListener("click", onOkClick);
      overlay?.removeEventListener("click", onOverlay);
      if (cb) cb();
    };

    const onCancelClick = () => close(onCancel);
    const onOkClick = () => close(onConfirm);
    const overlay = el.querySelector("[data-sa-close]");
    const onOverlay = () => close(onCancel);

    cancelBtn.addEventListener("click", onCancelClick);
    okBtn.addEventListener("click", onOkClick);
    overlay?.addEventListener("click", onOverlay);

    __CONFIRM_POPUP_OPEN = true;
    el.classList.add("is-open");
    el.setAttribute("aria-hidden", "false");
  }

  async function deleteArticle(productId) {
    const url = `${API_BASE}/api/item/${encodeURIComponent(productId)}/delete`;

    // Backend lit $_POST['articleId'] → on envoie en x-www-form-urlencoded
    const body = new URLSearchParams();
    body.set("articleId", String(productId));

    const res = await fetch(url, {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
      },
      body,
    });

    // On essaie de parser la réponse (même en cas d'erreur)
    let data = {};
    try {
      data = await res.json();
    } catch {}

    if (res.status === 401) {
      throw new Error(data.message || "User not logged in.");
    }
    if (res.status === 403) {
      throw new Error(
        data.message || "You are not authorized to delete this article."
      );
    }
    if (!res.ok) {
      throw new Error(data.message || `Delete failed ${res.status}`);
    }

    // attendu: { message: 'Your article has been successfully deleted.' }
    return data;
  }

  // ---------- Helpers ----------
  function normId(x) {
    const n = Number(x);
    return Number.isFinite(n) ? n : Number.parseInt(String(x).trim(), 10);
  }

  // Pluralisation FR
  const FR_PL = new Intl.PluralRules("fr");
  const isOne = (n) => FR_PL.select(Number(n)) === "one";
  const motFavori = (n) => (isOne(n) ? "favori" : "favoris");
  const pronomIls = (n) => (isOne(n) ? "il" : "ils");
  const verbeEtre = (n) => (isOne(n) ? "n’est pas" : "ne sont pas");
  const appartiennent = (n) => (isOne(n) ? "n’appartient" : "n’appartiennent");
  const dispoMot = (n) => (isOne(n) ? "disponible" : "disponibles");

  // Normalise product_ids depuis /api/me/likes (string CSV, string JSON, array, array d'objets)
  function normalizeLikedIds(product_ids) {
    if (!product_ids) return [];
    // String JSON: '["210","211"]'
    if (typeof product_ids === "string" && /^\s*\[/.test(product_ids)) {
      try {
        const arr = JSON.parse(product_ids);
        return Array.isArray(arr)
          ? arr.map(normId).filter(Number.isFinite)
          : [];
      } catch {
        // si parse échoue, on retombe plus bas
      }
    }
    // String CSV: "210,211"
    if (typeof product_ids === "string") {
      return product_ids.split(",").map(normId).filter(Number.isFinite);
    }
    // Array d'objets: [{product_id:210}, {id:"211"}]
    if (Array.isArray(product_ids) && typeof product_ids[0] === "object") {
      return product_ids
        .map((o) => normId(o.product_id ?? o.id))
        .filter(Number.isFinite);
    }
    // Array simple: [210,"211"]
    if (Array.isArray(product_ids)) {
      return product_ids.map(normId).filter(Number.isFinite);
    }
    return [];
  }

  // Mappe le "product" (structure /api/show/:id) vers un card
  function mapShowProductToCard(product) {
    if (!product) return null;

    // On ne garde que les produits "active"
    if (String(product.status || "").toLowerCase() !== "active") {
      return null;
    }

    const avg = Number(product.average_rating);
    const reviewCount = Number(product.review_count);

    return {
      id: normId(product.id),
      title: product.title || "",
      image: pickImage(product),
      price: product.price,
      original_price: product.original_price,
      formatted_price: product.formatted_price,
      currency: product.currency,
      converted_price: product.converted_price,
      city: product.city_name || "",
      shop: product.seller_fullname || "Softadastra",
      // Étoiles : arrondi à l’entier, entre 0 et 5
      stars: Number.isFinite(avg)
        ? Math.max(0, Math.min(5, Math.round(avg)))
        : 0,
      // Nombre d’avis
      sold: Number.isFinite(reviewCount) ? reviewCount : 0,
      // Badge NEW ou BOOST
      badge: String(product.condition_name || "")
        .toLowerCase()
        .includes("new")
        ? "NEW"
        : Number(product.boost) > 0
        ? "BOOST"
        : "",
      // Compteur de likes (optionnel)
      likes_count: Number(product.likes_count) || 0,
    };
  }

  // Récupère 1 produit via /api/show/:id et renvoie directement un "card"
  async function fetchProductById(id) {
    const r = await fetch(`${API_BASE}/api/show/${id}`, {
      credentials: "include",
    });
    if (!r.ok) return null;
    try {
      const data = await r.json(); // { product, user }
      return mapShowProductToCard(data?.product);
    } catch {
      return null;
    }
  }

  // --- Config Node (assure-toi d’avoir window.SA_NODE_API = "http://localhost:3000")
  function authFetchOpts() {
    return {
      credentials: "include",
      headers: {
        Accept: "application/json",
      },
    };
  }

  // 🔹 récupère les compteurs de likes pour une liste d'IDs
  async function fetchLikesCounts(ids) {
    if (!ids.length) return {};
    const url = nodeUrl(
      `/api/products/likes?ids=${encodeURIComponent(ids.join(","))}`
    );
    const res = await fetch(url, {
      credentials: "include",
      headers: { Accept: "application/json" },
    });
    if (!res.ok) throw new Error("likes.batch failed");
    const data = await res.json();
    const map = new Map();
    for (const [k, v] of Object.entries(data?.counts || {})) {
      map.set(Number(k), Number(v) || 0);
    }
    return map;
  }

  async function updateFavoritesBadge() {
    try {
      const sellerItems = await fetchUserProducts(PROFILE_USER_ID, {
        limit: 1_000,
      });
      const ids = sellerItems.map((p) => Number(p.id)).filter(Number.isFinite);
      if (!ids.length) {
        const favBadge = document.getElementById("favorites-badge");
        if (favBadge) {
          favBadge.textContent = "";
          favBadge.style.display = "none";
        }
        return;
      }

      const likeMap = await fetchLikesCounts(ids);
      const items = sellerItems.filter(
        (p) => (likeMap.get(Number(p.id)) || 0) > 0
      );

      const favBadge = document.getElementById("favorites-badge");
      if (favBadge) {
        if (items.length > 0) {
          favBadge.textContent = String(items.length);
          favBadge.style.display = "inline-block";
        } else {
          favBadge.textContent = "";
          favBadge.style.display = "none";
        }
      }
    } catch (e) {
      console.error("updateFavoritesBadge error:", e);
    }
  }

  // ----------------- Favorites (Seller's Popular items) -----------------
  async function renderFavorites() {
    // --- Shell UI
    view.innerHTML = `
    <section>
      <h3 style="margin:6px 0 10px;">Favorites</h3>
      <div id="grid-favs" class="listings-grid"></div>
      <div id="favs-cta" style="text-align:center;margin:12px 0;display:none;">
        <button id="favs-see-more" class="sa-btn sa-btn--primary" type="button">See more</button>
      </div>
      <div id="favs-loader" style="text-align:center;padding:12px;display:none;">Loading…</div>
    </section>
  `;

    const grid = view.querySelector("#grid-favs");
    const ctaBox = view.querySelector("#favs-cta");
    const ctaBtn = view.querySelector("#favs-see-more");
    const loader = view.querySelector("#favs-loader");

    // === Empty / Error states injected OUTSIDE the grid ===
    function injectAfterGrid(html, role = "status") {
      grid.style.display = "none";
      const next = grid.nextElementSibling;
      if (next && next.classList?.contains("favs-empty-block")) return; // avoid duplicates
      const box = document.createElement("div");
      box.className = "favs-empty-block";
      box.setAttribute("role", role);
      box.innerHTML = html;
      grid.insertAdjacentElement("afterend", box);
      ctaBox.style.display = "none";
    }

    function showEmptyState({ title, text, svg }) {
      injectAfterGrid(
        `
      <div style="text-align:center;padding:20px;border:1px dashed #e7e9ee;border-radius:12px;">
        ${svg}
        <div style="font-weight:700;color:#0b0c0f;margin-bottom:4px;">${title}</div>
        <div style="color:#667085;font-size:.95rem;">${text}</div>
      </div>
    `,
        "status"
      );
    }

    function showErrorState() {
      injectAfterGrid(
        `
      <div style="text-align:center;padding:20px;border:1px dashed #e7e9ee;border-radius:12px;">
        <svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true"
             style="display:block;margin:0 auto 10px;color:#9aa1ac;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round;">
          <!-- Alert triangle -->
          <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          <path d="M12 9v4"></path>
          <path d="M12 17h.01"></path>
        </svg>
        <div style="font-weight:700;color:#0b0c0f;margin-bottom:4px;">Unable to load favorites</div>
        <div style="color:#667085;font-size:.95rem;">Please try again later.</div>
      </div>
    `,
        "alert"
      );
    }

    // If later we do have items, restore grid and remove empty block
    function restoreGridIfHidden() {
      if (grid.style.display === "none") {
        grid.style.display = "";
        const next = grid.nextElementSibling;
        if (next && next.classList?.contains("favs-empty-block")) next.remove();
      }
    }

    // --- Pagination config (client-side)
    const LIMIT_FIRST = 5;
    const LIMIT_NEXT = 10;
    let nextIndex = 0;
    let isLoading = false;
    let hasMore = true;

    // --- Helpers
    const nfCompact = new Intl.NumberFormat("en", {
      notation: "compact",
      compactDisplay: "short",
      maximumFractionDigits: 1,
    });
    const fmtCount = (n = 0) =>
      nfCompact
        .format(n)
        .replace(/K\b/i, "k")
        .replace(/M\b/i, "m")
        .replace(/B\b/i, "b");

    function appendCardsHTML(htmlList) {
      const frag = document.createDocumentFragment();
      for (const html of htmlList) {
        const wrap = document.createElement("div");
        wrap.innerHTML = html;
        if (wrap.firstElementChild) frag.appendChild(wrap.firstElementChild);
      }
      grid.appendChild(frag);
    }

    function cardHTML(it) {
      return `
      <article class="card" data-product-id="${
        it.id
      }" style="content-visibility:auto;contain-intrinsic-size:300px 400px;">
        <a href="/item/${it.id}" style="text-decoration:none;color:inherit;">
          <div class="media">
            <img loading="lazy" decoding="async" fetchpriority="low"
                 src="${it.image}"
                 srcset="${it.image}?w=200 200w, ${it.image}?w=400 400w, ${
        it.image
      }?w=600 600w"
                 sizes="(max-width:600px) 45vw, (max-width:1024px) 30vw, 220px"
                 alt="${it.title}">
            <!-- Like overlay -->
            <button type="button"
                    class="sa-like"
                    aria-label="${
                      it.likes_count === 1
                        ? "1 person likes this item"
                        : (it.likes_count || 0) + " people like this item"
                    }"
                    title="${
                      it.likes_count === 1
                        ? "1 person likes this item"
                        : (it.likes_count || 0) + " people like this item"
                    }">
              <svg class="sa-like__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M12 21s-6.5-4.35-9.33-7.2C1.1 12.2 1 9.5 3 7.5c1.9-1.9 4.9-1.7 6.9.3L12 9l2.1-1.2c2-2 5-2.3 6.9-.3s1.9 4.7.3 6.3C18.5 16.65 12 21 12 21z" fill="currentColor"></path>
              </svg>
              <span class="sa-like__count" data-like-count="${
                it.likes_count || 0
              }">${fmtCount(it.likes_count || 0)}</span>
            </button>
          </div>

          <div class="card-body">
            <h4 class="title clamp-2" title="${it.title}">${it.title}</h4>
            <div class="price-block">${formatPriceBlock(it)}</div>
            <div class="rating" title="${
              it.sold
            } reviews, rating ${(Number.isFinite(it.avg) ? it.avg : 0).toFixed(
        1
      )}/5">
              ${renderStarsFromAvg(Number.isFinite(it.avg) ? it.avg : it.stars)}
              <span class="note">${
                Number.isFinite(it.avg)
                  ? it.avg.toFixed(1)
                  : (it.stars || 0).toFixed(1)
              }</span>
              <span class="count">(${it.sold})</span>
            </div>
            <div class="meta">
              ${it.city ? `<span>${it.city}</span>` : ``}
              ${it.shop ? `<span>${it.shop}</span>` : ``}
            </div>
          </div>
        </a>
      </article>`;
    }

    // --- Fetch + compute data once, then paginate locally
    let items = [];
    try {
      const sellerItems = await fetchUserProducts(PROFILE_USER_ID, {
        limit: 200,
      });

      // No active listings for this seller
      if (!sellerItems?.length) {
        showEmptyState({
          title: "No active listings",
          text: "This seller hasn’t published any active items yet.",
          svg: `
          <svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true"
               style="display:block;margin:0 auto 10px;color:#9aa1ac;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round;">
            <!-- Storefront -->
            <path d="M3 9l1-5h16l1 5v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9zM5 4l-.6 3h15.2L19 4z"></path>
            <path d="M7 13h10M7 17h7"></path>
          </svg>`,
        });
        hasMore = false;
        return;
      }

      const ids = sellerItems.map((p) => Number(p.id)).filter(Number.isFinite);
      const likeMap = await fetchLikesCounts(ids); // Map<id, count>

      items = sellerItems
        .map((p) => ({
          ...p,
          likes_count: likeMap.get(Number(p.id)) || 0,
        }))
        .filter((p) => p.likes_count > 0)
        .sort((a, b) => b.likes_count - a.likes_count);

      // No favorites yet
      if (!items.length) {
        showEmptyState({
          title: "No favorites yet",
          text: "None of this seller’s products have been added to favorites yet.",
          svg: `
          <svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true"
               style="display:block;margin:0 auto 10px;color:#9aa1ac;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round;">
            <!-- Heart -->
            <path d="M12 21s-6.5-4.35-9.33-7.2C1.1 12.2 1 9.5 3 7.5c1.9-1.9 4.9-1.7 6.9.3L12 9l2.1-1.2c2-2 5-2.3 6.9-.3s1.9 4.7.3 6.3C18.5 16.65 12 21 12 21z"></path>
          </svg>`,
        });
        hasMore = false;
        return;
      }

      // If we have items, make sure grid is visible (in case it was hidden)
      restoreGridIfHidden();
    } catch (e) {
      console.error("renderFavorites error:", e);
      showErrorState();
      hasMore = false;
      return;
    }

    // --- Local paging
    async function loadMoreLocal(limit) {
      if (isLoading || !hasMore) return;
      isLoading = true;
      loader.style.display = "block";

      try {
        const slice = items.slice(nextIndex, nextIndex + limit);
        if (!slice.length) {
          hasMore = false;
          return;
        }
        appendCardsHTML(slice.map(cardHTML));
        nextIndex += slice.length;
        if (nextIndex >= items.length) hasMore = false;
      } finally {
        isLoading = false;
        loader.style.display = "none";
      }
    }

    // --- 1) First render
    await loadMoreLocal(LIMIT_FIRST);
    ctaBox.style.display = hasMore ? "block" : "none";

    // --- 2) Infinite scroll (after click)
    let startedScroll = false;
    let observer = null;

    function startInfiniteScroll() {
      if (startedScroll) return;
      startedScroll = true;

      const sentinel = document.createElement("div");
      sentinel.id = "favs-sentinel";
      grid.after(sentinel);

      observer = new IntersectionObserver(
        (entries) => {
          if (entries[0].isIntersecting) {
            loadMoreLocal(LIMIT_NEXT);
          }
        },
        {
          rootMargin: "300px",
        }
      );

      observer.observe(sentinel);
    }

    ctaBtn?.addEventListener("click", async () => {
      ctaBox.style.display = "none";
      await loadMoreLocal(LIMIT_NEXT);
      if (hasMore) startInfiniteScroll();
    });
  }

  // ============================
  // Config & helpers (global)
  // ============================

  function q(sel, root = document) {
    return root.querySelector(sel);
  }

  function withTimeout(promiseFactory, ms = 12000, label = "request") {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), ms);
    return Promise.race([
      (async () => {
        try {
          const res = await promiseFactory(ctrl.signal);
          clearTimeout(t);
          return res;
        } catch (e) {
          clearTimeout(t);
          throw new Error(`${label} timeout/abort: ${e.message || e}`);
        }
      })(),
    ]);
  }

  async function fetchJSON(url, opts = {}) {
    const run = (signal) =>
      fetch(url, {
        credentials: "include",
        signal,
        ...opts,
      });
    const res = await withTimeout(run, 15000, `GET ${url}`);
    if (!res.ok) throw new Error(`HTTP ${res.status} ${res.statusText}`);
    return res.json();
  }

  // Transforme un "view" (Element ou sélecteur) en Element, sinon lève une erreur claire
  function asElement(elOrSelector) {
    if (elOrSelector instanceof Element) return elOrSelector;
    if (typeof elOrSelector === "string") {
      const el = document.querySelector(elOrSelector);
      if (el) return el;
    }
    throw new Error(
      "renderReviews: container introuvable (view). Passe un Element valide ou un sélecteur existant."
    );
  }

  // Monte/retourne un mount #reviews-view sous le container global `view`
  function ensureReviewsMount(parentCandidate) {
    const parent =
      (parentCandidate instanceof Element && parentCandidate) ||
      (typeof parentCandidate === "string" &&
        document.querySelector(parentCandidate)) ||
      (typeof window !== "undefined" && typeof view !== "undefined" && view) ||
      document.body;

    let el = parent.querySelector("#reviews-view");
    if (!el) {
      el = document.createElement("div");
      el.id = "reviews-view";
      parent.appendChild(el);
    }
    return el;
  }

  function starsHTML(n) {
    const full = Math.floor(n || 0);
    const half = n - full >= 0.5 ? 1 : 0;
    const empty = 5 - full - half;
    return `<span class="sa-stars" aria-label="${n || 0}/5">
    ${"★".repeat(full)}${half ? "⯨" : ""}${"☆".repeat(Math.max(0, empty))}
  </span>`;
  }

  function timeAgo(dtStr) {
    const d = new Date(dtStr);
    if (Number.isNaN(+d)) return "";
    const secs = Math.floor((Date.now() - d.getTime()) / 1000);
    const mins = Math.floor(secs / 60),
      hrs = Math.floor(mins / 60),
      days = Math.floor(hrs / 24);
    if (secs < 60) return "à l’instant";
    if (mins < 60) return `il y a ${mins} min`;
    if (hrs < 24) return `il y a ${hrs} h`;
    return `il y a ${days} j`;
  }

  function skeletonCard() {
    return `
  <article class="review skel">
    <div class="head">
      <div class="avatar-skel"></div>
      <div class="who">
        <div class="line-skel w80"></div>
        <div class="line-skel w40"></div>
      </div>
    </div>
    <div class="line-skel w60" style="margin:8px 0 6px;"></div>
    <div class="line-skel w100"></div>
    <div class="line-skel w90"></div>
  </article>`;
  }

  function reviewCard(r) {
    const author = r.author || r.author_name || r.user_name || "Buyer";
    const avatar =
      r.author_photo ||
      r.avatar ||
      r.avatar_url ||
      r.user_avatar ||
      "https://i.pravatar.cc/80?img=15";
    const rating = Number(r.stars ?? r.rating ?? 0);
    const text = r.text || r.comment || r.message || "";
    const createdAt = r.created_at || r.time || "";

    return `
  <article class="review" style="content-visibility:auto;contain-intrinsic-size:220px 240px;">
    <div class="head">
      <img loading="lazy" decoding="async" fetchpriority="low"
           src="${avatar}" width="44" height="44" alt="${author}">
      <div class="who">
        <span class="name">${author}</span>
        <span class="time">${
          createdAt ? timeAgo(createdAt) : r.time ?? "recently"
        }</span>
      </div>
    </div>
    <div class="stars">${starsHTML(rating)}</div>
    <div class="text">${text}</div>
  </article>`;
  }

  function renderHeader(container, stats) {
    const avg = Number(stats?.average_rating ?? 0).toFixed(1);
    const count = Number(stats?.review_count ?? 0);
    container.innerHTML = `
    <section class="sa-rev-section">
      <header class="sa-rev-header">
        <div class="sa-rev-title">
          <h3>Évaluations</h3>
          <p class="muted">${count} avis • Moyenne ${avg}/5 ${starsHTML(
      avg
    )}</p>
        </div>
        <div class="sa-rev-actions">
          <label class="sa-rev-order">
            Tri :
            <select id="saRevOrder">
              <option value="DESC" selected>Plus récents</option>
              <option value="ASC">Plus anciens</option>
            </select>
          </label>
        </div>
      </header>
      <div id="grid-reviews" class="reviews"></div>
      <div class="sa-rev-footer">
        <button id="saRevLoadMore" class="sa-btn">Charger plus</button>
      </div>
    </section>
  `;
  }

  function chunkRenderIntoFallback(container, rows, { chunk = 10 } = {}) {
    let i = 0;

    function step() {
      const slice = rows.slice(i, i + chunk).join("");
      container.insertAdjacentHTML("beforeend", slice);
      i += chunk;
      if (i < rows.length) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  // ============================
  // Reviews d'un VENDEUR (par userId)
  // ============================
  function resolveSellerId(explicitId) {
    // 1) param direct
    const p = Number(explicitId);
    if (Number.isFinite(p) && p > 0) return p;

    // 2) variable globale présente sur la page vendeur
    const g = Number(window.PROFILE_USER_ID || window.SA_PROFILE_USER_ID);
    if (Number.isFinite(g) && g > 0) return g;

    // 3) data-* dans le DOM
    const el = document.querySelector("[data-user-id],[data-seller-id]");
    if (el) {
      const v = Number(el.dataset.userId ?? el.dataset.sellerId);
      if (Number.isFinite(v) && v > 0) return v;
    }

    // 4) querystring
    const qs = new URLSearchParams(location.search);
    for (const key of ["user_id", "seller_id", "uid"]) {
      const v = Number(qs.get(key));
      if (Number.isFinite(v) && v > 0) return v;
    }

    // 5) pathname: /seller/42, /user/42, /profile/42
    const m = location.pathname.match(
      /\/(seller|user|profile)\/(\d+)(?:\/|$)/i
    );
    if (m && Number.isFinite(Number(m[2]))) return Number(m[2]);

    return 0;
  }

  function ensureMount(id) {
    let el = view.querySelector(`#${id}`);
    if (!el) {
      el = document.createElement("section");
      el.id = id;
      view.innerHTML = ""; // on nettoie le conteneur de l’onglet courant
      view.appendChild(el);
    } else {
      view.innerHTML = ""; // on ne garde qu'un mount
      view.appendChild(el);
    }
    return el;
  }

  function setBadge(el, count) {
    if (!el) return;
    const n = Number(count) || 0;
    if (n > 0) {
      el.textContent = String(n);
      el.style.display = "inline-block";
    } else {
      el.textContent = "";
      el.style.display = "none";
    }
  }

  async function updateReviewsBadgeBoot() {
    try {
      const sellerId =
        typeof resolveSellerId === "function"
          ? resolveSellerId()
          : PROFILE_USER_ID;
      const url = `/api/user/${encodeURIComponent(sellerId)}/reviews/stats`;
      // si tu as déjà fetchJSON() global, utilise-le :
      const stats =
        typeof fetchJSON === "function"
          ? await fetchJSON(url)
          : await (
              await fetch(url, {
                credentials: "include",
              })
            ).json();

      const count = Number(stats?.review_count ?? 0);
      setBadge(document.getElementById("reviews-badge"), count);
    } catch (e) {
      console.warn("updateReviewsBadgeBoot error:", e);
      setBadge(document.getElementById("reviews-badge"), 0);
    }
  }

  // Signature tolérante :
  // - renderReviews()                      -> auto-mount dans view + auto sellerId
  // - renderReviews(view)                  -> auto sellerId
  // - renderReviews(view, sellerId)        -> ok
  // - renderReviews(view, sellerId, opts)  -> ok
  async function renderReviews({ token } = {}) {
    if (isStale(token)) return;

    const mount = ensureMount("reviews-view");

    // header skeleton local à l’onglet (pas le skeleton "produits")
    mount.innerHTML = `
    <section class="sa-rev-section">
      <header class="sa-rev-header">
        <div class="sa-rev-title">
          <h3>Évaluations</h3>
          <p class="muted">Chargement…</p>
        </div>
      </header>
      <div id="grid-reviews" class="reviews">
        ${Array.from({ length: 6 }).map(skeletonCard).join("")}
      </div>
      <div class="sa-rev-footer">
        <button id="saRevLoadMore" class="sa-btn" disabled>Charger plus</button>
      </div>
    </section>
  `;
    if (isStale(token)) return;

    // fetch stats
    let stats = {
      average_rating: 0,
      review_count: 0,
    };
    try {
      stats = await fetchJSON(`/api/user/${resolveSellerId()}/reviews/stats`);
      // ✅ MAJ badge à chaud
      setBadge(
        document.getElementById("reviews-badge"),
        Number(stats?.review_count ?? 0)
      );
    } catch (e) {
      console.warn(e);
    }
    if (isStale(token)) return;

    // maj header sans toucher 'view'
    const title = mount.querySelector(".sa-rev-title .muted");
    if (title) {
      const avg = Number(stats?.average_rating ?? 0).toFixed(1);
      const count = Number(stats?.review_count ?? 0);
      title.innerHTML = `${count} avis • Moyenne ${avg}/5 ${starsHTML(avg)}`;
    }

    // page 1
    await loadPage(1);

    async function loadPage(p) {
      const url = new URL(
        `/api/user/${resolveSellerId()}/reviews`,
        location.origin
      );
      url.searchParams.set("limit", "10");
      url.searchParams.set("page", String(p));
      url.searchParams.set("order", "DESC");

      const data = await fetchJSON(url.toString());
      if (isStale(token)) return;

      const items = data?.items || [];
      const grid = mount.querySelector("#grid-reviews");

      if (!items.length && p === 1) {
        mount.innerHTML = `
      <div class="about-box about-empty" role="status" aria-live="polite" style="text-align:center;padding:20px;">
        <svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true"
             style="display:block;margin:0 auto 10px;color:#9aa1ac;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round;">
          <!-- Star / review icon -->
          <path d="M12 3.5l2.47 5 5.53.8-4 3.9.94 5.5L12 16.9 7.06 18.7 8 13.9l-4-3.9 5.53-.8L12 3.5z"></path>
        </svg>
        <div style="font-weight:700;color:#0b0c0f;margin-bottom:4px;">No reviews yet</div>
        <div style="color:#667085;font-size:.95rem;">When buyers leave reviews, they’ll appear here.</div>
      </div>`;
        return;
      }

      if (p === 1) grid.innerHTML = "";

      const rows = items.map(reviewCard);
      if (typeof window.chunkRenderInto === "function") {
        window.chunkRenderInto(grid, rows, {
          chunk: 10,
          delay: 16,
          gridClass: "reviews",
        });
      } else {
        grid.insertAdjacentHTML("beforeend", rows.join(""));
      }
    }
  }

  async function renderAbout() {
    const bio = document.getElementById("user-bio")?.textContent?.trim() || "";
    const name =
      document.getElementById("user-fullname")?.textContent?.trim() ||
      "Utilisateur";
    const join = ""; // Pas de champ dans le DOM pour la date d'inscription
    const loc = ""; // Pas de champ localisation dans ton HTML
    const total = ""; // Pas de champ ventes totales dans ton HTML
    const avatar =
      document.getElementById("profile_image_preview")?.src ||
      "https://via.placeholder.com/100?text=User";

    view.innerHTML = `
      <section class="about-box sa-about-section">
          <div class="sa-about-header">
              <img src="${avatar}" alt="${name}" class="sa-about-avatar">
              <div>
                  <h3 class="sa-about-name">${name}</h3>
                  ${
                    loc
                      ? `<p class="sa-about-loc"><i class="fas fa-map-marker-alt"></i> ${loc}</p>`
                      : ""
                  }
                  ${
                    join
                      ? `<p class="sa-about-join">Membre depuis ${join}</p>`
                      : ""
                  }
              </div>
          </div>
          <div class="sa-about-bio">
              <h4>À propos</h4>
              <p>${
                bio || "Ce vendeur n’a pas encore renseigné de description."
              }</p>
          </div>
          <div class="sa-about-stats">
              ${
                total
                  ? `<div><strong>${total}</strong><span>ventes réalisées</span></div>`
                  : ""
              }
              <div><strong>★</strong><span>Voir évaluations</span></div>
          </div>
      </section>
      `;
  }

  async function renderProShop({ token }) {
    if (isStale(token)) return;

    // ---- Helpers (inchangés) ----
    const setStatusChip = (el, statusText) => {
      if (!el) return;
      el.textContent = statusText || "-";
      el.classList.add("chip");
      el.classList.remove("chip--pending", "chip--approved", "chip--rejected");
      const s = String(statusText || "").toLowerCase();
      if (s.includes("pending") || s.includes("attente"))
        el.classList.add("chip--pending");
      else if (
        s.includes("approved") ||
        s.includes("active") ||
        s.includes("actif")
      )
        el.classList.add("chip--approved");
      else if (s.includes("rejected") || s.includes("rejet"))
        el.classList.add("chip--rejected");
    };

    const statusIconSVG = (status) => {
      const s = String(status || "").toLowerCase();
      if (s === "approved" || s === "active" || s === "actif") {
        return `<svg viewBox="0 0 24 24" aria-hidden="true" fill="#28a745"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm-1 14l-4-4 1.41-1.41L11 13.17l5.59-5.59L18 9l-7 7z"/></svg>`;
      }
      if (s === "pending" || s.includes("attente")) {
        return `<svg viewBox="0 0 24 24" aria-hidden="true" fill="#f59e0b"><path d="M6 2h12v2l-4 6v2l4 6v2H6v-2l4-6v-2L6 4V2z"/></svg>`;
      }
      if (s === "rejected" || s.includes("rejet")) {
        return `<svg viewBox="0 0 24 24" aria-hidden="true" fill="#dc3545"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>`;
      }
      return `<svg viewBox="0 0 24 24" aria-hidden="true" fill="#6b7280"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>`;
    };

    // ---- API statut ----
    let apiData = null;
    try {
      const res = await fetch("/api/seller/subscription/status", {
        credentials: "same-origin",
      });
      const json = await res.json().catch(() => null);
      if (res.ok && json?.success) apiData = json.data || null;
    } catch (_) {}

    const pickDisplaySource = (data) => {
      if (!data) return null;
      const req = data.subscription_request || data.subscription || null;
      const act = data.current || data.active || null;

      if (req && String(req.status || "").toLowerCase() !== "approved")
        return {
          plan: req.plan,
          status: req.status,
        };
      if (act)
        return {
          plan: act.plan,
          status: act.effective_status || act.status,
        };
      if (req)
        return {
          plan: req.plan,
          status: req.status,
        };
      return null;
    };

    const chosen = pickDisplaySource(apiData);
    const chosenPlan = chosen?.plan || null;
    const chosenStatus = (chosen?.status || "").toLowerCase();

    // ---- CTAs minimalistes (on pousse vers /pricing) ----
    let ctaPrimaryHTML = "";
    let ctaSecondaryHTML = "";

    if (!chosen) {
      ctaPrimaryHTML = `<a href="/pricing" class="sa-btn"><i class="fas fa-tags"></i> View plans</a>`;
      ctaSecondaryHTML = `<a href="/shop/home" class="sa-btn-outline"><i class="fas fa-wrench"></i> Go to dashboard</a>`;
    } else if (chosenStatus === "pending" || chosenStatus.includes("attente")) {
      ctaPrimaryHTML = `<a class="sa-btn pending-disabled disabled"><i class="fas fa-hourglass-half"></i> Waiting for review</a>`;
      ctaSecondaryHTML = `<a href="/pricing" class="sa-btn-outline"><i class="fas fa-tags"></i> Change plan</a>`;
    } else if (["approved", "active", "actif"].includes(chosenStatus)) {
      ctaPrimaryHTML = `<a class="sa-btn success-disabled disabled"><i class="fas fa-check"></i> Plan active</a>`;
      ctaSecondaryHTML = `<a href="/shop/home" class="sa-btn-outline"><i class="fas fa-wrench"></i> Go to dashboard</a>`;
    } else if (chosenStatus === "rejected" || chosenStatus.includes("rejet")) {
      ctaPrimaryHTML = `<a href="/pricing" class="sa-btn"><i class="fas fa-sync-alt"></i> Resubmit proof</a>`;
      ctaSecondaryHTML = `<a class="sa-btn-outline rejected-disabled disabled"><i class="fas fa-ban"></i> Rejected</a>`;
    } else {
      ctaPrimaryHTML = `<a href="/pricing" class="sa-btn"><i class="fas fa-tags"></i> View plans</a>`;
      ctaSecondaryHTML = `<a href="/shop/home" class="sa-btn-outline"><i class="fas fa-wrench"></i> Go to dashboard</a>`;
    }

    // ---- UI minimaliste (sans badges ni liste de features) ----
    const statusCardHTML = chosen
      ? `
    <div class="subscription-status-wrapper">
      <div id="currentSubscriptionStatus" class="subscription-status-card">
        <div class="status-icon" id="statusIcon">${statusIconSVG(
          chosenStatus
        )}</div>
        <div class="status-content">
          <div class="status-title">
            Current subscription: <strong id="currentPlan">${
              chosenPlan || "-"
            }</strong>
          </div>
          <div class="status-row">
            <span class="status-label">Status</span>
            <span id="currentStatus" class="chip">-</span>
          </div>
        </div>
      </div>
    </div>`
      : "";

    view.innerHTML = `
    ${statusCardHTML}
    <section class="proshop-box sa-proshop-section">
      <div class="sa-proshop-header">
        <i class="fas fa-briefcase"></i>
        <h3>Professional Shop</h3>
      </div>
      <p class="sa-proshop-subtitle">Manage your shop. Plans & details are available on the pricing page.</p>

      <div class="sa-proshop-actions" style="margin-top:12px;">
        ${ctaPrimaryHTML}
        ${ctaSecondaryHTML}
      </div>
    </section>
  `;

    // ---- Post-render : chip statut (texte concis, pas de note détaillée) ----
    if (chosen && !isStale(token)) {
      const chip = document.getElementById("currentStatus");
      setStatusChip(chip, chosenStatus);
    }
  }

  // ---- Boot (onglet par défaut) ----
  navigate(DEFAULT_TAB, {
    smooth: false,
    scroll: false,
  });
})();
