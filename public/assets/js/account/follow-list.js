// =============== Dropdown settings ===============
function toggleDropdown() {
  const menu = document.getElementById("settings-menu");
  if (!menu) return;
  const isOpen = menu.style.display === "block";
  menu.style.display = isOpen ? "none" : "block";

  const rect = menu.getBoundingClientRect();
  if (rect.bottom > window.innerHeight) {
    menu.style.top = "auto";
    menu.style.bottom = "110%";
  } else {
    menu.style.top = "110%";
    menu.style.bottom = "auto";
  }
}

document.addEventListener("click", (e) => {
  const dd = e.target.closest(".settings-dropdown");
  const menu = document.getElementById("settings-menu");
  if (!dd && menu) menu.style.display = "none";
});

// =============== Follow list ===============
const loader = document.getElementById("loader");
const userList = document.getElementById("user-list");
const titleEl = document.getElementById("follow-title");
let listAbort = null;

const showLoader = (on) => {
  if (loader) loader.style.display = on ? "block" : "none";
};
const setTitle = (t) => {
  if (titleEl) titleEl.textContent = t;
};

async function loadFollowList(type, id) {
  if (!id || (type !== "followers" && type !== "following")) return;

  // Abort précédente si besoin
  if (listAbort) listAbort.abort();
  listAbort = new AbortController();

  showLoader(true);
  if (userList) userList.innerHTML = "";
  setTitle(`Loading ${type}...`);

  try {
    const res = await fetch(`/api/get-${type}/${encodeURIComponent(id)}`, {
      credentials: "include",
      cache: "no-store",
      signal: listAbort.signal,
    });

    if (res.status === 401) {
      setTitle("Please sign in to view this list.");
      showLoader(false);
      return;
    }

    const data = await res.json().catch(() => ({}));
    const users = Array.isArray(data[type]) ? data[type] : [];
    setTitle(
      `${type === "followers" ? "Followers" : "Following"} (${users.length})`
    );
    showLoader(false);

    if (!userList) return;

    if (users.length === 0) {
      const p = document.createElement("p");
      p.textContent =
        type === "followers"
          ? "No followers yet."
          : "You’re not following anyone.";
      p.style.textAlign = "center";
      p.style.color = "#777";
      userList.appendChild(p);
      return;
    }

    const frag = document.createDocumentFragment();
    users.forEach((u) => {
      const li = document.createElement("li");
      li.className = "fl-card";

      const img = document.createElement("img");
      img.className = "fl-avatar";
      img.loading = "lazy";
      img.src = u.photo || "/public/images/profile/avatar.jpg";
      img.alt = u.username || "user";

      const info = document.createElement("div");
      info.className = "fl-info";

      const name = document.createElement("div");
      name.className = "fl-name";
      name.textContent = u.username || "—";

      const meta = document.createElement("div");
      meta.className = "fl-meta";
      if (u.country_image_url) {
        const flag = document.createElement("img");
        flag.className = "fl-flag";
        flag.src = `/public/images/pays/${u.country_image_url}`;
        flag.alt = "";
        meta.appendChild(flag);
      }
      if (u.city_name) {
        const city = document.createElement("span");
        city.textContent = u.city_name;
        meta.appendChild(city);
      }
      info.appendChild(name);
      info.appendChild(meta);

      const kpis = document.createElement("div");
      kpis.className = "fl-kpis";
      const k1 = document.createElement("div");
      k1.className = "fl-kpi";
      k1.innerHTML = `<span class="num">${Number(
        u.followers_count || 0
      )}</span><span class="lab">Followers</span>`;
      const k2 = document.createElement("div");
      k2.className = "fl-kpi";
      k2.innerHTML = `<span class="num">${Number(
        u.following_count || 0
      )}</span><span class="lab">Following</span>`;
      kpis.appendChild(k1);
      kpis.appendChild(k2);

      const action = document.createElement("div");
      action.className = "fl-action";
      const view = document.createElement("button");
      view.type = "button";
      view.className = "fl-view";
      view.textContent = "View profile";
      view.addEventListener("click", () => {
        if (u.username)
          window.location.href = `/profile/${encodeURIComponent(u.username)}`;
      });
      action.appendChild(view);

      li.appendChild(img);
      li.appendChild(info);
      li.appendChild(kpis);
      li.appendChild(action);
      frag.appendChild(li);
    });

    userList.appendChild(frag);
  } catch (err) {
    if (err?.name === "AbortError") return;
    console.error(err);
    showLoader(false);
    setTitle("Error loading users.");
  }
}

// =============== Tabs ===============
function toggleTabs(type) {
  document.getElementById("tab-followers")?.classList.remove("active");
  document.getElementById("tab-following")?.classList.remove("active");
  document.getElementById(`tab-${type}`)?.classList.add("active");

  const url = new URL(location.href);
  url.searchParams.set("type", type);
  history.replaceState(null, "", url.toString());

  loadFollowList(type, window.userId);
}

window.addEventListener("DOMContentLoaded", () => {
  window.userId = document.body?.dataset?.userId || "";
  const defaultType =
    typeof type === "string" && (type === "followers" || type === "following")
      ? type
      : new URLSearchParams(location.search).get("type") || "followers";
  toggleTabs(defaultType);
});

document
  .getElementById("tab-followers")
  ?.addEventListener("click", () => toggleTabs("followers"));
document
  .getElementById("tab-following")
  ?.addEventListener("click", () => toggleTabs("following"));
