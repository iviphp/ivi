IVI_PAGES.register("user.create", {
  mount(ctx) {
    const form = ctx.qs("form[data-spa-form]");
    if (!form) return;

    const globalError = ctx.qs("#user-create-global-error");

    const clearErrors = () => {
      if (globalError) {
        globalError.classList.add("d-none");
        globalError.textContent = "";
      }
      form
        .querySelectorAll(".is-invalid")
        .forEach((el) => el.classList.remove("is-invalid"));
      form
        .querySelectorAll("[data-err]")
        .forEach((el) => (el.textContent = ""));
    };

    const applyErrors = (errors) => {
      clearErrors();
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

    const onSuccess = async (ev) => {
      const d = ev.detail || {};
      if (d.form !== form) return;

      // ton API renvoie { ok:true, redirect:"/users/ID" }
      const redirect =
        d.data?.redirect || d.headers?.["x-spa-redirect"] || null;
      if (redirect) {
        await ctx.spa.go(redirect, { pushState: true, allowCache: false });
        return;
      }

      // fallback: aller à /users
      await ctx.spa.go("/users", { pushState: true, allowCache: false });
    };

    const onError = (ev) => {
      const d = ev.detail || {};
      if (d.form !== form) return;

      clearErrors();

      // Validation 422
      if (d.status === 422) {
        const errors = d.data?.errors || d.error?.errors || null;
        applyErrors(errors);
        return;
      }

      // Autres erreurs
      const msg =
        d.data?.message || d.error?.message || d.error || "Request failed.";
      if (globalError) {
        globalError.textContent = String(msg);
        globalError.classList.remove("d-none");
      } else {
        alert(msg);
      }
    };

    document.addEventListener("ivi:form:success", onSuccess);
    document.addEventListener("ivi:form:error", onError);

    ctx.addCleanup(() => {
      document.removeEventListener("ivi:form:success", onSuccess);
      document.removeEventListener("ivi:form:error", onError);
    });

    // utile si tu reviens sur la page
    clearErrors();
  },
});
