IVI_PAGES.register("user.edit", {
  async mount(ctx) {
    const shell = ctx.qs("#user-edit-shell");
    if (!shell) return;

    const globalError = ctx.qs("#user-edit-global-error");
    const userId = parseInt(shell.getAttribute("data-user-id") || "0", 10);
    if (!userId) {
      shell.innerHTML = `<div class="alert alert-danger">Invalid user id.</div>`;
      return;
    }

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

    const clearErrors = (form) => {
      clearGlobal();
      form
        .querySelectorAll(".is-invalid")
        .forEach((el) => el.classList.remove("is-invalid"));
      form
        .querySelectorAll("[data-err]")
        .forEach((el) => (el.textContent = ""));
    };

    const applyErrors = (form, errors) => {
      clearErrors(form);
      if (!errors || typeof errors !== "object") return;

      for (const field of Object.keys(errors)) {
        const messages = Array.isArray(errors[field])
          ? errors[field]
          : [String(errors[field])];
        const input = form.querySelector(`[name="${field}"]`);
        const box = form.querySelector(`[data-err="${field}"]`);
        if (input) input.classList.add("is-invalid");
        if (box) box.textContent = messages.join(", ");
      }
    };

    // 1) Load user
    try {
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
      shell.innerHTML = renderForm(u);

      const form = shell.querySelector("form[data-spa-form]");
      if (!form) return;

      // 2) hook events emitted by forms.js
      const onSuccess = async (ev) => {
        const d = ev.detail || {};
        if (d.form !== form) return;

        const redirect = d.data?.redirect || null;
        if (redirect) {
          await ctx.spa.go(redirect, { pushState: true, allowCache: false });
          return;
        }
        await ctx.spa.go(`/users/${userId}`, {
          pushState: true,
          allowCache: false,
        });
      };

      const onError = (ev) => {
        const d = ev.detail || {};
        if (d.form !== form) return;

        // validation 422
        if (d.status === 422) {
          const errors = d.data?.errors || d.error?.errors || null;
          applyErrors(form, errors);
          return;
        }

        clearErrors(form);
        showGlobal(d.data?.message || d.error?.message || "Update failed.");
      };

      document.addEventListener("ivi:form:success", onSuccess);
      document.addEventListener("ivi:form:error", onError);

      ctx.addCleanup(() => {
        document.removeEventListener("ivi:form:success", onSuccess);
        document.removeEventListener("ivi:form:error", onError);
      });

      clearErrors(form);
    } catch (e) {
      shell.innerHTML = `<div class="alert alert-danger">Unexpected error.</div>`;
    }
  },
});

function renderForm(u) {
  const id = u.id || 0;
  const name = escapeHtml(u.name || "");
  const email = escapeHtml(u.email || "");
  const activeChecked = u.active ? "checked" : "";

  return `
<form
  data-spa-form
  action="/api/users/${id}"
  method="post"
  data-spa-disable="true"
  data-spa-reset="false"
  novalidate
>
  <!-- if your backend doesn't support PATCH directly, use _method -->
  <input type="hidden" name="_method" value="PATCH">
  <input type="hidden" name="csrf_token" value="${escapeHtml(
    getCsrfToken() || ""
  )}">

  <div class="mb-3">
    <label class="form-label">Name</label>
    <input class="form-control" type="text" name="name" value="${name}" required>
    <div class="invalid-feedback" data-err="name"></div>
  </div>

  <div class="mb-3">
    <label class="form-label">Email</label>
    <input class="form-control" type="email" name="email" value="${email}" required>
    <div class="invalid-feedback" data-err="email"></div>
  </div>

  <div class="mb-3">
    <label class="form-label">New password (optional)</label>
    <input class="form-control" type="password" name="password" placeholder="Leave blank to keep current">
    <div class="invalid-feedback" data-err="password"></div>
  </div>

  <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="active" value="1" id="activeCheck" ${activeChecked}>
    <label class="form-check-label" for="activeCheck">Active</label>
    <div class="invalid-feedback d-block" data-err="active"></div>
  </div>

  <div class="d-flex gap-2">
    <button class="btn btn-primary" type="submit">Update</button>
    <a class="btn btn-outline-secondary" data-spa href="/users">Cancel</a>
  </div>
</form>
`;
}

function getCsrfToken() {
  const m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute("content") : "";
}

function escapeHtml(s) {
  return String(s)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
