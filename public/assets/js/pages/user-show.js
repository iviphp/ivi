IVI_PAGES.register("user.show", {
  async mount(ctx) {
    const shell = ctx.qs("#user-show-shell");
    if (!shell) return;

    const globalError = ctx.qs("#user-show-global-error");
    const userId = parseInt(shell.getAttribute("data-user-id") || "0", 10);

    const showGlobal = (msg) => {
      if (!globalError) return alert(msg);
      globalError.textContent = String(msg || "Request failed.");
      globalError.classList.remove("d-none");
    };

    const clearGlobal = () => {
      if (!globalError) return;
      globalError.classList.add("d-none");
      globalError.textContent = "";
    };

    if (!userId) {
      shell.innerHTML = `<div class="alert alert-danger">Invalid user id.</div>`;
      return;
    }

    try {
      clearGlobal();
      shell.innerHTML = `<div class="text-muted">Loading user…</div>`;

      const res = await fetch(`/api/users/${userId}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
      });

      const json = await res.json();
      if (!res.ok || !json.ok) {
        shell.innerHTML = `<div class="alert alert-danger">Failed to load user.</div>`;
        return;
      }

      const u = json.data || {};
      shell.innerHTML = renderUser(u);
    } catch (e) {
      showGlobal("Unexpected error.");
      shell.innerHTML = `<div class="alert alert-danger">Failed to load user.</div>`;
    }
  },
});

IVI_ACTIONS.register("user.delete", async ({ data, spa }) => {
  const id = data.int("userId");
  if (!id) return;

  if (!confirm("Delete this user?")) return;

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
    const redirect = json.redirect || "/users";
    await spa.go(redirect, { pushState: true, allowCache: false });
  } else {
    alert(json?.message || "Delete failed.");
  }
});

function renderUser(u) {
  const id = escapeHtml(u.id ?? "");
  const name = escapeHtml(u.name ?? "");
  const email = escapeHtml(u.email ?? "");
  const active = u.active ? "yes" : "no";

  return `
<ul class="list-group">
  <li class="list-group-item"><strong>Name:</strong> ${name}</li>
  <li class="list-group-item"><strong>Email:</strong> ${email}</li>
  <li class="list-group-item"><strong>Active:</strong> ${active}</li>
</ul>
`;
}

function escapeHtml(s) {
  return String(s)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
