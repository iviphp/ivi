IVI_PAGES.register("user.index", {
  async mount(ctx) {
    const shell = ctx.qs("#users-index-shell");
    const metaEl = ctx.qs("#users-index-meta");
    const pagEl = ctx.qs("#users-index-pagination");
    const globalError = ctx.qs("#users-index-global-error");

    if (!shell || !pagEl) return;

    const clearGlobal = () => {
      if (!globalError) return;
      globalError.classList.add("d-none");
      globalError.textContent = "";
    };

    const showGlobal = (msg) => {
      if (!globalError) return alert(msg);
      globalError.textContent = String(msg || "Request failed.");
      globalError.classList.remove("d-none");
    };

    const url = new URL(location.href);
    const page = parseInt(url.searchParams.get("page") || "1", 10);
    const per = parseInt(url.searchParams.get("per_page") || "5", 10);

    try {
      clearGlobal();
      shell.innerHTML = `<div class="text-muted">Loading users…</div>`;

      const res = await fetch(`/api/users?page=${page}&per_page=${per}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
      });

      const json = await res.json();
      if (!res.ok || !json.ok) {
        shell.innerHTML = `<div class="alert alert-danger">Failed to load users.</div>`;
        return;
      }

      const users = Array.isArray(json.data) ? json.data : [];
      shell.innerHTML = renderTable(users);

      const meta = json.meta || {};
      if (metaEl) {
        const total = meta.total ?? 0;
        const cur = meta.page ?? page;
        const totalPages = meta.total_pages ?? 1;
        metaEl.textContent = `Total: ${total} — Page ${cur} / ${totalPages}`;
      }

      renderPagination(pagEl, meta);
    } catch (e) {
      showGlobal("Unexpected error.");
      shell.innerHTML = `<div class="alert alert-danger">Failed to load users.</div>`;
    }
  },
});

IVI_ACTIONS.register("user.delete", async ({ data, spa }) => {
  const id = data.int("userId");
  if (!id) return;

  if (!confirm("Delete user?")) return;

  const res = await fetch(`/api/users/${id}`, {
    method: "DELETE",
    headers: { "X-Requested-With": "XMLHttpRequest" },
    credentials: "same-origin",
  });

  let json = null;
  try {
    json = await res.json();
  } catch {}

  if (res.ok && json?.ok) {
    // refresh same page (no push)
    await spa.go(location.pathname + location.search, {
      pushState: false,
      allowCache: false,
    });
  } else {
    alert(json?.message || "Delete failed.");
  }
});

function renderTable(users) {
  const rows = users.length
    ? users
        .map((u) => {
          const id = u.id ?? 0;
          const name = escapeHtml(u.name ?? "");
          const email = escapeHtml(u.email ?? "");
          const active = u.active ? "yes" : "no";

          return `
<tr>
  <td>${id}</td>
  <td>${name}</td>
  <td>${email}</td>
  <td>${active}</td>
  <td class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" data-spa href="/users/${id}">show</a>
    <a class="btn btn-outline-primary btn-sm" data-spa href="/users/${id}/edit">edit</a>
    <button class="btn btn-outline-danger btn-sm" data-action="user.delete" data-user-id="${id}">delete</button>
  </td>
</tr>`;
        })
        .join("")
    : `<tr><td colspan="5" class="text-muted">No users.</td></tr>`;

  return `
<table class="table table-striped table-bordered align-middle">
  <thead>
    <tr>
      <th style="width:80px;">ID</th>
      <th>Name</th>
      <th>Email</th>
      <th style="width:90px;">Active</th>
      <th style="width:220px;">Actions</th>
    </tr>
  </thead>
  <tbody>
    ${rows}
  </tbody>
</table>`;
}

function renderPagination(ul, meta) {
  const page = meta?.page || 1;
  const per = meta?.per_page || 5;
  const totalPages = meta?.total_pages || 1;

  const item = (href, label, disabled = false, active = false) => `
<li class="page-item ${disabled ? "disabled" : ""} ${active ? "active" : ""}">
  <a class="page-link" data-spa href="${href}">${label}</a>
</li>`;

  const mkUrl = (p) => {
    const u = new URL(location.href);
    u.searchParams.set("page", String(p));
    u.searchParams.set("per_page", String(per));
    return u.pathname + "?" + u.searchParams.toString();
  };

  let html = "";

  const firstDisabled = page <= 1;
  const lastDisabled = page >= totalPages;

  html += item(mkUrl(1), "« First", firstDisabled);
  html += item(mkUrl(Math.max(1, page - 1)), "‹ Prev", firstDisabled);

  const start = Math.max(1, page - 2);
  const end = Math.min(totalPages, page + 2);
  for (let p = start; p <= end; p++) {
    html += item(mkUrl(p), String(p), false, p === page);
  }

  html += item(mkUrl(Math.min(totalPages, page + 1)), "Next ›", lastDisabled);
  html += item(mkUrl(totalPages), "Last »", lastDisabled);

  ul.innerHTML = html;
}

function escapeHtml(s) {
  return String(s)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
