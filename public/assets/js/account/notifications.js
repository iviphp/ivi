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

// ---- Config pagination
let currentOffset = 0;
const limit = 15;
let isLoadingNotifs = false; // ✅ anti double fetch
let lastPageReached = false; // ✅ stop après la dernière page

// ---- Helpers Node
const NODE_BASE =
  window.SA_NODE_BASE ||
  document.querySelector('meta[name="sa-node-base"]')?.content ||
  "https://api.softadastra.com";

const nodeUrl = (p = "") =>
  NODE_BASE.replace(/\/+$/, "") + "/" + String(p).replace(/^\/+/, "");

// ---- ID utilisateur (essaie plusieurs sources)
let CURRENT_UID = ""; // sera résolu avant le premier load

function joinUrl(base, path) {
  return (
    base.replace(/\/+$/, "") + "/" + String(path || "").replace(/^\/+/, "")
  );
}

async function resolveCurrentUid() {
  // 1) cache
  const cached = localStorage.getItem("sa:uid");
  if (cached) {
    CURRENT_UID = String(cached);
    return CURRENT_UID;
  }

  // 2) fetch PHP /get-user
  const phpBase =
    window.SA_PHP_BASE || location.origin || "https://softadastra.com";
  const getUserPath = window.ENDPOINTS?.php?.getUser || "/get-user";
  const url = getUserPath.startsWith("http")
    ? getUserPath
    : joinUrl(phpBase, getUserPath);

  try {
    const res = await fetch(
      url + (url.includes("?") ? "&" : "?") + "ts=" + Date.now(),
      {
        credentials: "include",
        headers: { Accept: "application/json" },
        cache: "no-store",
      }
    );
    if (!res.ok) throw new Error("HTTP " + res.status);
    const j = await res.json(); // ton exemple: { id, fullname, ... }
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

// ---- timeAgo (fallback simple si pas déjà défini dans ta page)
function timeAgo(ts) {
  try {
    const d =
      typeof ts === "string" || typeof ts === "number" ? new Date(ts) : ts;
    const s = Math.floor((Date.now() - d.getTime()) / 1000);
    if (s < 60) return `${s}s ago`;
    const m = Math.floor(s / 60);
    if (m < 60) return `${m}m ago`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h ago`;
    const dd = Math.floor(h / 24);
    return `${dd}d ago`;
  } catch {
    return "";
  }
}

// ---- JSON safe
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

// ---- UI helpers
function hideLoadMore() {
  const box = document.getElementById("load-more-container");
  if (box) box.style.display = "none";
}
function showLoadMore() {
  if (lastPageReached) return hideLoadMore();
  const box = document.getElementById("load-more-container");
  if (box) box.style.display = "block";
}
function setEmptyMessage(listEl, msg = "You have no notifications for now.") {
  if (listEl) listEl.innerHTML = `<p>${msg}</p>`;
}
function listIsEmpty(listEl) {
  return !listEl || !listEl.querySelector(".notif-item");
}
function decGlobalNotifBadge(count = 1) {
  try {
    if (window.SA_Badges?.set) {
      const cur = window.SA_Badges.get?.().notif ?? 0;
      window.SA_Badges.set("notif", Math.max(0, cur - count));
    }
    const b = document.getElementById("notif-badge");
    if (b) {
      const v = Math.max(0, (parseInt(b.textContent || "0", 10) || 0) - count);
      b.textContent = String(v);
      b.style.display = v > 0 ? "inline-block" : "none";
    }
  } catch {}
}

// ---- Route builder
function getNotificationUrl(type, relatedId) {
  switch (type) {
    case "chat":
      return relatedId ? `/chat/conversation/${relatedId}` : "/chat/home";
    case "order":
      return relatedId ? `/user/orders/${relatedId}` : "/user/orders";
    case "payment":
      return relatedId ? `/user/payments/${relatedId}` : "/user/payments";
    default:
      return null;
  }
}

// --- Charge l'historique (append = true pour paginer)
async function loadNotifications(append = false) {
  const list = document.getElementById("notification-list");
  if (!list) return;
  if (!CURRENT_UID) {
    if (!append) setEmptyMessage(list, "User not identified.");
    hideLoadMore();
    return;
  }
  if (isLoadingNotifs || lastPageReached) return;
  isLoadingNotifs = true;

  const url = nodeUrl(
    `/api/notifications/history/${encodeURIComponent(
      CURRENT_UID
    )}?offset=${currentOffset}&limit=${limit}`
  );

  try {
    const res = await fetch(url, { credentials: "include", cache: "no-store" });
    if (!res.ok) {
      if (!append) setEmptyMessage(list, `Error ${res.status} while loading.`);
      hideLoadMore();
      lastPageReached = true;
      return;
    }
    const data = await toJSONSafe(res);
    const items = Array.isArray(data?.notifications) ? data.notifications : [];

    if (!append) list.innerHTML = "";

    if (items.length === 0) {
      if (!append && listIsEmpty(list)) setEmptyMessage(list);
      hideLoadMore();
      lastPageReached = true;
      return;
    }

    for (const n of items) {
      const item = document.createElement("div");
      item.className = "notif-item" + (n.seen ? "" : " unseen");
      item.dataset.notifId = n.id;

      const icon =
        n.type === "chat"
          ? "💬"
          : n.type === "order"
          ? "📦"
          : n.type === "payment"
          ? "💰"
          : "🔔";

      const targetUrl = getNotificationUrl(n.type, n.related_id);

      item.innerHTML = `
        <input type="checkbox" class="notif-checkbox" aria-label="Select notification"/>
        <div class="notif-icon" aria-hidden="true">${icon}</div>
        <div class="notif-content">
          <strong>${(n.title || "").toString()}</strong><br>
          <span>${(n.body || "").toString()}</span><br>
          <small>${n.created_at ? timeAgo(n.created_at) : ""}</small>
        </div>
        <div class="notif-actions-buttons">
          <button class="notif-delete" title="Delete">🗑</button>
        </div>
      `;

      // Navigation (ignore clics sur checkbox / bouton)
      item.addEventListener("click", async (e) => {
        const t = e.target;
        if (
          t.classList?.contains("notif-delete") ||
          t.classList?.contains("notif-checkbox")
        )
          return;

        // Marque vue côté UI
        if (item.classList.contains("unseen")) {
          item.classList.remove("unseen");
          decGlobalNotifBadge(1);
        }
        if (targetUrl) window.location.href = targetUrl;
      });

      // Suppression
      item
        .querySelector(".notif-delete")
        ?.addEventListener("click", async (e) => {
          e.stopPropagation();
          try {
            const delUrl = nodeUrl(
              `/api/notifications/delete/${encodeURIComponent(n.id)}`
            );
            const r = await fetch(delUrl, {
              method: "DELETE",
              credentials: "include",
            });
            if (r.ok) {
              // Si elle était "unseen", décrémente les badges
              if (item.classList.contains("unseen")) decGlobalNotifBadge(1);
              item.remove();
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

      list.appendChild(item);
    }

    // Pagination: si moins que limit => dernière page
    if (items.length < limit) {
      lastPageReached = true;
      hideLoadMore();
    } else {
      currentOffset += items.length; // ✅ avance de la taille réellement reçue
      showLoadMore();
    }
  } catch (err) {
    console.error("loadNotifications error:", err);
    if (!append) setEmptyMessage(list, "Unable to load notifications.");
    hideLoadMore();
  } finally {
    isLoadingNotifs = false;
  }
}
(async function bootNotifs() {
  const list = document.getElementById("notification-list");

  if (!CURRENT_UID) CURRENT_UID = await resolveCurrentUid();

  if (!CURRENT_UID) {
    setEmptyMessage(list, "User not identified.");
    hideLoadMore();
    return;
  }
  // On lance le premier chargement (append=false)
  loadNotifications(false);
})();
// ---- Actions qui ont besoin d’un user_id: sécurise en résolvant si nécessaire
document
  .getElementById("mark-all-read")
  ?.addEventListener("click", async () => {
    if (!CURRENT_UID) CURRENT_UID = await resolveCurrentUid();
    if (!CURRENT_UID) return;

    try {
      const url = nodeUrl(
        `/api/notifications/read-all/${encodeURIComponent(CURRENT_UID)}`
      );
      const r = await fetch(url, { method: "POST", credentials: "include" });
      if (!r.ok) throw new Error("read-all failed");

      document
        .querySelectorAll(".notif-item.unseen")
        .forEach((el) => el.classList.remove("unseen"));
      try {
        window.SA_Badges?.set?.("notif", 0);
        const b = document.getElementById("notif-badge");
        if (b) {
          b.textContent = "0";
          b.style.display = "none";
        }
      } catch {}
    } catch (err) {
      console.error("mark-all-read error:", err);
    }
  });

document.getElementById("load-more")?.addEventListener("click", async () => {
  if (!CURRENT_UID) CURRENT_UID = await resolveCurrentUid();
  if (!CURRENT_UID) return;
  if (!lastPageReached) loadNotifications(true);
});

document
  .getElementById("delete-selected")
  ?.addEventListener("click", async () => {
    if (!CURRENT_UID) CURRENT_UID = await resolveCurrentUid();
    if (!CURRENT_UID) return;

    const boxes = document.querySelectorAll(".notif-checkbox:checked");
    if (!boxes.length) return;
    if (
      !confirm(
        `Delete ${boxes.length} notification${boxes.length > 1 ? "s" : ""}?`
      )
    )
      return;

    let removed = 0;
    for (const cb of boxes) {
      const row = cb.closest(".notif-item");
      const id = row?.dataset.notifId;
      if (!id) continue;
      try {
        const delUrl = nodeUrl(
          `/api/notifications/delete/${encodeURIComponent(id)}`
        );
        const r = await fetch(delUrl, {
          method: "DELETE",
          credentials: "include",
        });
        if (r.ok) {
          if (row.classList.contains("unseen")) removed += 1;
          row.remove();
        }
      } catch (err) {
        console.error("bulk delete notif error:", err);
      }
    }
    if (removed > 0) decGlobalNotifBadge(removed);

    const list = document.getElementById("notification-list");
    if (listIsEmpty(list)) {
      setEmptyMessage(list);
      hideLoadMore();
    }
  });
