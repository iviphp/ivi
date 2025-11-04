function timeAgo(dateString) {
  const now = new Date();
  const then = new Date(dateString);
  const seconds = Math.floor((now - then) / 1000);

  const intervals = [
    {
      label: "an",
      seconds: 31536000,
    },
    {
      label: "mois",
      seconds: 2592000,
    },
    {
      label: "semaine",
      seconds: 604800,
    },
    {
      label: "jour",
      seconds: 86400,
    },
    {
      label: "heure",
      seconds: 3600,
    },
    {
      label: "minute",
      seconds: 60,
    },
    {
      label: "seconde",
      seconds: 1,
    },
  ];

  for (let i of intervals) {
    const count = Math.floor(seconds / i.seconds);
    if (count > 0) {
      return `il y a ${count} ${i.label}${count > 1 ? "s" : ""}`;
    }
  }
  return "à l’instant";
}
// =======================
// Notifications Page JS
// =======================

// ------- CONFIG GÉNÉRALE -------
const NOTIF_SOURCE = "php"; // "node" | "php"  (tu pourras ajouter "both" plus tard)
const PAGE_LIMIT = 15;

// Bases URL
const PHP_BASE =
  window.SA_PHP_BASE || location.origin || "https://softadastra.com";
const NODE_BASE =
  window.SA_NODE_BASE ||
  document.querySelector('meta[name="sa-node-base"]')?.content ||
  "https://api.softadastra.com";

// Helpers URL
const phpUrl = (p = "") =>
  PHP_BASE.replace(/\/+$/, "") + "/" + String(p).replace(/^\/+/, "");
const nodeUrl = (p = "") =>
  NODE_BASE.replace(/\/+$/, "") + "/" + String(p).replace(/^\/+/, "");

// ------- ÉTAT LOCAL -------
let currentOffset = 0;
let isLoading = false;
let lastPage = false;
let CURRENT_UID = ""; // requis pour Node

// ------- HELPERS GÉNÉRIQUES -------
function $(sel, root = document) {
  return root.querySelector(sel);
}

function joinUrl(base, path) {
  return (
    base.replace(/\/+$/, "") + "/" + String(path || "").replace(/^\/+/, "")
  );
}

function setEmptyMessage(listEl, msg = "You have no notifications for now.") {
  if (!listEl) return;
  listEl.innerHTML = `<p class="notif-empty">${msg}</p>`;
}

function listIsEmpty(listEl) {
  return !listEl || !listEl.querySelector(".notif-item");
}

function hideLoadMore() {
  const box = $("#load-more-container");
  if (box) box.style.display = "none";
}

function showLoadMore() {
  if (lastPage) return hideLoadMore();
  const box = $("#load-more-container");
  if (box) box.style.display = "block";
}

function decGlobalNotifBadge(count = 1) {
  try {
    if (window.SA_Badges?.set) {
      const cur = window.SA_Badges.get?.().notif ?? 0;
      window.SA_Badges.set("notif", Math.max(0, cur - count));
    }
    const b = $("#notif-badge");
    if (b) {
      const v = Math.max(0, (parseInt(b.textContent || "0", 10) || 0) - count);
      b.textContent = String(v);
      b.style.display = v > 0 ? "inline-block" : "none";
    }
  } catch {}
}
async function toJSONSafe(res) {
  const ct = (res.headers.get("content-type") || "").toLowerCase();
  const txt = await res.text();
  if (!ct.includes("application/json")) {
    throw new Error(
      `Non-JSON response (${res.status}) : ${txt.slice(0, 120)}…`
    );
  }
  try {
    return JSON.parse(txt);
  } catch {
    throw new Error("Invalid JSON");
  }
}

// timeAgo avec Intl.RelativeTimeFormat si dispo
const rtf =
  typeof Intl !== "undefined" && Intl.RelativeTimeFormat
    ? new Intl.RelativeTimeFormat(document.documentElement.lang || "en", {
        numeric: "auto",
      })
    : null;

function timeAgo(ts) {
  try {
    const d =
      typeof ts === "string" || typeof ts === "number" ? new Date(ts) : ts;
    const diffSec = Math.floor((Date.now() - d.getTime()) / 1000);
    if (!rtf) {
      if (diffSec < 60) return `${diffSec}s ago`;
      const m = Math.floor(diffSec / 60);
      if (m < 60) return `${m}m ago`;
      const h = Math.floor(m / 60);
      if (h < 24) return `${h}h ago`;
      const dd = Math.floor(h / 24);
      return `${dd}d ago`;
    } else {
      if (diffSec < 60) return rtf.format(-diffSec, "second");
      const m = Math.floor(diffSec / 60);
      if (m < 60) return rtf.format(-m, "minute");
      const h = Math.floor(m / 60);
      if (h < 24) return rtf.format(-h, "hour");
      const dd = Math.floor(h / 24);
      if (dd < 30) return rtf.format(-dd, "day");
      const mo = Math.floor(dd / 30);
      if (mo < 12) return rtf.format(-mo, "month");
      const y = Math.floor(mo / 12);
      return rtf.format(-y, "year");
    }
  } catch {
    return "";
  }
}

// ------- RÉSOLVE USER (Node seulement) -------
async function resolveCurrentUid() {
  const cached = localStorage.getItem("sa:uid");
  if (cached) {
    CURRENT_UID = String(cached);
    return CURRENT_UID;
  }

  const getUserPath = window.ENDPOINTS?.php?.getUser || "/get-user";
  const url = getUserPath.startsWith("http")
    ? getUserPath
    : joinUrl(PHP_BASE, getUserPath);

  try {
    const res = await fetch(
      url + (url.includes("?") ? "&" : "?") + "ts=" + Date.now(),
      {
        credentials: "include",
        headers: {
          Accept: "application/json",
        },
        cache: "no-store",
      }
    );
    if (!res.ok) throw new Error("HTTP " + res.status);
    const j = await res.json();
    const id = (j?.user?.id ?? j?.id ?? j?.user_id ?? "").toString();
    if (id) {
      CURRENT_UID = id;
      try {
        localStorage.setItem("sa:uid", id);
      } catch {}
      return id;
    }
  } catch (e) {
    console.warn("resolveCurrentUid() failed:", e);
  }
  return "";
}

// ------- CONSTRUCTION DES ENDPOINTS -------
function notifHistoryUrl(offset, limit) {
  if (NOTIF_SOURCE === "php") {
    // GET /api/account/notifications/list?offset=&limit=
    return phpUrl(
      `/api/account/notifications/list?offset=${offset}&limit=${limit}`
    );
  }
  // Node: GET /api/notifications/history/:uid?offset=&limit=
  return nodeUrl(
    `/api/notifications/history/${encodeURIComponent(
      CURRENT_UID
    )}?offset=${offset}&limit=${limit}`
  );
}

function notifDeleteUrl(id) {
  if (NOTIF_SOURCE === "php")
    return phpUrl(
      `/api/account/notifications/delete/${encodeURIComponent(id)}`
    );
  return nodeUrl(`/api/notifications/delete/${encodeURIComponent(id)}`);
}

function notifReadAllUrl() {
  if (NOTIF_SOURCE === "php") return phpUrl(`/api/notifications/mark-all-read`);
  return nodeUrl(
    `/api/notifications/read-all/${encodeURIComponent(CURRENT_UID)}`
  );
}

// ------- NORMALISATION DES ITEMS -------
function normalizeItem(n) {
  // Node: {id, type, title, body, related_id, seen, created_at, ...}
  // PHP : {id, type, title, body, related_id, is_read, created_at, ...}
  return {
    id: n.id,
    type: n.type || n.notification_type || "system",
    title: n.title || n.message_title || "Notification",
    body: n.body || n.message || "",
    related_id: n.related_id ?? n.target_id ?? null,
    seen: typeof n.seen === "boolean" ? n.seen : !!n.is_read,
    created_at: n.created_at || n.createdAt || n.ts || null,
  };
}

// ------- ROUTAGE DÉTAIL / CLIC -------
function getNotificationUrl(type, relatedId) {
  switch (type) {
    case "chat":
      return relatedId ? `/chat/conversation/${relatedId}` : "/chat/home";
    case "order":
      return relatedId ? `/user/orders/${relatedId}` : "/user/orders";
    case "payment":
      return relatedId ? `/user/payments/${relatedId}` : "/user/payments";
    case "follow":
      return relatedId ? `/profile/${relatedId}` : "/profile";
    default:
      return null;
  }
}

// ===== SVG helper (icônes pro)
function getTypeIconSvg(type) {
  const common =
    'width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"';
  switch (type) {
    case "chat": // 💬
      return `<svg ${common}><path d="M4 6.5A3.5 3.5 0 0 1 7.5 3h9A3.5 3.5 0 0 1 20 6.5v6A3.5 3.5 0 0 1 16.5 16H12l-4 4v-4H7.5A3.5 3.5 0 0 1 4 12.5v-6Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`;
    case "order": // 📦
      return `<svg ${common}><path d="M3 7.5 12 3l9 4.5-9 4.5L3 7.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M21 7.5v8L12 20.9 3 15.5v-8" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 12V3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`;
    case "payment": // 💳
      return `<svg ${common}><rect x="3" y="5" width="18" height="14" rx="2.5" stroke="currentColor" stroke-width="1.6"/><path d="M3 9.5h18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><rect x="6.5" y="13" width="5" height="2.5" rx="1.2" fill="currentColor"/></svg>`;
    case "follow": // ⭐ / user-plus
      return `<svg ${common}><circle cx="12" cy="8" r="3.25" stroke="currentColor" stroke-width="1.6"/><path d="M5.5 19.5a6.5 6.5 0 0 1 13 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M18.5 7v-2m0 0v-2m0 2h2m-2 0h-2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`;
    default: // 🔔
      return `<svg ${common}><path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 5 2 6H4c.5-1 2-2 2-6Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 19a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`;
  }
}
const TRASH_SVG = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11v6M14 11v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`;

// ===== RENDER ITEM (pro + dataset.type)
function renderItem(n) {
  const item = document.createElement("div");
  item.className = "notif-item" + (n.seen ? "" : " unseen");
  item.dataset.notifId = n.id;
  item.dataset.type = n.type || "system"; // ✅ ligne ajoutée (pour filtres)

  const iconSvg = getTypeIconSvg(n.type);
  const targetUrl = getNotificationUrl(n.type, n.related_id);

  item.innerHTML = `
    <input type="checkbox" class="notif-checkbox" aria-label="Select notification"/>
    <div class="notif-icon" aria-hidden="true">${iconSvg}</div>
    <div class="notif-content">
      <strong>${(n.title || "").toString()}</strong><br>
      <span>${(n.body || "").toString()}</span><br>
      <small>${n.created_at ? timeAgo(n.created_at) : ""}</small>
    </div>
    <div class="notif-actions-buttons">
      <button class="notif-delete" title="Delete" aria-label="Delete notification">${TRASH_SVG}</button>
    </div>
  `;

  // Navigation (ignore checkbox/bouton)
  item.addEventListener("click", (e) => {
    const t = e.target;
    if (
      t.classList?.contains("notif-delete") ||
      t.closest(".notif-delete") ||
      t.classList?.contains("notif-checkbox")
    )
      return;
    if (item.classList.contains("unseen")) {
      item.classList.remove("unseen");
      decGlobalNotifBadge(1);
    }
    if (targetUrl) window.location.href = targetUrl;
  });

  // Suppression
  item.querySelector(".notif-delete")?.addEventListener("click", async (e) => {
    e.stopPropagation();
    try {
      const r = await fetch(notifDeleteUrl(n.id), {
        method: "DELETE",
        credentials: "include",
      });
      if (r.ok) {
        if (item.classList.contains("unseen")) decGlobalNotifBadge(1);
        item.remove();
        const list = document.getElementById("notification-list");
        if (listIsEmpty(list)) {
          setEmptyMessage(list);
          hideLoadMore();
        }
      } else {
        console.error("Delete notif failed:", r.status);
      }
    } catch (err) {
      console.error("Delete notif error:", err);
    }
  });

  return item;
}

// ------- CHARGEMENT (HISTORIQUE + PAGINATION) -------
async function loadNotifications(append = false) {
  const list = $("#notification-list");
  if (!list) return;

  if (NOTIF_SOURCE !== "php" && !CURRENT_UID) {
    if (!append) setEmptyMessage(list, "User not identified.");
    hideLoadMore();
    return;
  }
  if (isLoading || lastPage) return;
  isLoading = true;

  // petit skeleton
  let skeleton;
  if (!append && listIsEmpty(list)) {
    skeleton = document.createElement("div");
    skeleton.className = "notif-skeleton";
    skeleton.innerHTML = `<div class="notif-skel-line"></div><div class="notif-skel-line"></div><div class="notif-skel-line"></div>`;
    list.appendChild(skeleton);
  }

  const url = notifHistoryUrl(currentOffset, PAGE_LIMIT);

  try {
    const res = await fetch(url, {
      credentials: "include",
      cache: "no-store",
    });
    if (!res.ok) {
      if (!append) setEmptyMessage(list, `Error ${res.status} while loading.`);
      hideLoadMore();
      lastPage = true;
      return;
    }

    const data = await toJSONSafe(res);
    const raw = Array.isArray(data?.notifications)
      ? data.notifications
      : Array.isArray(data)
      ? data
      : [];
    const items = raw.map(normalizeItem);

    if (!append) list.innerHTML = "";

    if (items.length === 0) {
      if (!append && listIsEmpty(list)) setEmptyMessage(list);
      hideLoadMore();
      lastPage = true;
      return;
    }

    const frag = document.createDocumentFragment();
    for (const it of items) frag.appendChild(renderItem(it));
    list.appendChild(frag);

    if (items.length < PAGE_LIMIT) {
      lastPage = true;
      hideLoadMore();
    } else {
      currentOffset += items.length;
      showLoadMore();
    }
  } catch (err) {
    console.error("loadNotifications error:", err);
    if (!append)
      setEmptyMessage($("#notification-list"), "Unable to load notifications.");
    hideLoadMore();
  } finally {
    if (skeleton) skeleton.remove();
    isLoading = false;
  }
}

// ------- ACTIONS (MARK ALL / DELETE SELECTION / LOAD MORE) -------
async function handleMarkAllRead() {
  if (NOTIF_SOURCE !== "php" && !CURRENT_UID)
    CURRENT_UID = await resolveCurrentUid();
  try {
    const r = await fetch(notifReadAllUrl(), {
      method: "POST",
      credentials: "include",
    });
    if (!r.ok) throw new Error("read-all failed");
    document
      .querySelectorAll(".notif-item.unseen")
      .forEach((el) => el.classList.remove("unseen"));
    try {
      window.SA_Badges?.set?.("notif", 0);
      const b = $("#notif-badge");
      if (b) {
        b.textContent = "0";
        b.style.display = "none";
      }
    } catch {}
  } catch (err) {
    console.error("mark-all-read error:", err);
  }
}

async function handleDeleteSelected() {
  const boxes = document.querySelectorAll(".notif-checkbox:checked");
  if (!boxes.length) return;
  if (
    !confirm(
      `Delete ${boxes.length} notification${boxes.length > 1 ? "s" : ""}?`
    )
  )
    return;

  let removedUnseen = 0;
  for (const cb of boxes) {
    const row = cb.closest(".notif-item");
    const id = row?.dataset.notifId;
    if (!id) continue;
    try {
      const r = await fetch(notifDeleteUrl(id), {
        method: "DELETE",
        credentials: "include",
      });
      if (r.ok) {
        if (row.classList.contains("unseen")) removedUnseen += 1;
        row.remove();
      }
    } catch (err) {
      console.error("bulk delete notif error:", err);
    }
  }
  if (removedUnseen > 0) decGlobalNotifBadge(removedUnseen);

  const list = $("#notification-list");
  if (listIsEmpty(list)) {
    setEmptyMessage(list);
    hideLoadMore();
  }
}

// ------- BOOTSTRAP -------
(async function bootNotifs() {
  const list = $("#notification-list");
  if (!list) return;

  // Résoudre l'UID si on utilise Node
  if (NOTIF_SOURCE !== "php" && !CURRENT_UID) {
    CURRENT_UID = await resolveCurrentUid();
    if (!CURRENT_UID) {
      setEmptyMessage(list, "User not identified.");
      hideLoadMore();
      return;
    }
  }

  // Premier chargement
  await loadNotifications(false);

  // Boutons
  $("#mark-all-read")?.addEventListener("click", handleMarkAllRead);
  $("#delete-selected")?.addEventListener("click", handleDeleteSelected);
  $("#load-more")?.addEventListener("click", () => {
    if (!lastPage) loadNotifications(true);
  });

  // Auto-load quand le bouton "Load more" entre en vue
  const loadMoreBtn = $("#load-more");
  if ("IntersectionObserver" in window && loadMoreBtn) {
    const io = new IntersectionObserver(
      (entries) => {
        for (const e of entries) {
          if (e.isIntersecting && !lastPage && !isLoading) {
            loadNotifications(true);
          }
        }
      },
      {
        rootMargin: "256px 0px",
      }
    );
    io.observe(loadMoreBtn);
  }
})();
