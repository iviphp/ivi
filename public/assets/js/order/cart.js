(function () {
  const sub =
    parseFloat(
      (
        document.getElementById("softadastraSumSubtotal")?.textContent || "0"
      ).replace(/[^\d.]/g, "")
    ) || 0;
  const threshold = 100; // même valeur que PHP ci-dessus
  const shippingEl = document.getElementById("softadastraSumShipping");
  if (!shippingEl) return;
  shippingEl.textContent = sub >= threshold ? "$0.00" : "$7.90";
})();

(function () {
  const $ = (s, ctx = document) => ctx.querySelector(s);

  const itemsWrap = $("#softadastraCartItems");
  const sumQtyEl = $("#softadastraSumQty");
  const sumSubEl = $("#softadastraSumSubtotal");
  const btnClear = $("#softadastraBtnClear");
  const btnRefresh = $("#softadastraBtnRefresh");
  const btnCheckout = $("#softadastraBtnCheckout");

  async function post(url, data) {
    const res = await fetch(url, {
      method: "POST",
      headers: {
        Accept: "application/json",
      },
      body: new URLSearchParams(data),
    });
    let json = null;
    try {
      json = await res.json();
    } catch (e) {}
    if (!res.ok || !json || json.success === false) {
      throw new Error(json?.message || "Request failed");
    }
    return json;
  }

  function setCartCount(n) {
    const val = Math.max(0, Number(n) || 0);
    document
      .querySelectorAll("#cart-count, .js-cart-quantity")
      .forEach((el) => {
        el.textContent = String(val);
      });
  }

  async function handleUpdate(row) {
    const key = row.dataset.key;
    const qtyInput = row.querySelector(".softadastra-qty-input");
    const qty = Math.max(0, parseInt(qtyInput.value || "0", 10));
    row.style.opacity = ".6";
    try {
      const out = await post("/cart/update", {
        key,
        quantity: qty,
      });

      // totaux page
      sumQtyEl.textContent = String(out.cart_quantity ?? 0);
      sumSubEl.textContent = Number(out.cart_subtotal ?? 0).toFixed(2);

      // ✅ met à jour le badge header
      setCartCount(out?.cart_quantity ?? 0);

      if (qty === 0) {
        row.remove();
      } else {
        const priceUnitText =
          row.querySelector(".softadastra-ci-price").textContent || "";
        const priceUnit = parseFloat(priceUnitText.replace(/[^\d.]/g, "")) || 0;
        row.querySelector(".softadastra-ci-total").textContent =
          "$" + (priceUnit * qty).toFixed(2);
      }

      const any = !!itemsWrap.querySelector(".softadastra-cart-item");
      if (btnClear) btnClear.disabled = !any;
      if (btnCheckout) btnCheckout.disabled = !any;
      if (!any)
        itemsWrap.innerHTML =
          '<div class="softadastra-empty">Your cart is empty.</div>';
    } catch (err) {
      alert(err.message || "Update error");
    } finally {
      row.style.opacity = "";
    }
  }

  async function handleRemove(row) {
    const key = row.dataset.key;
    row.style.opacity = ".6";
    try {
      const out = await post("/cart/remove", {
        key,
      });

      // totaux page
      sumQtyEl.textContent = String(out.cart_quantity ?? 0);
      sumSubEl.textContent = Number(out.cart_subtotal ?? 0).toFixed(2);

      // ✅ badge header
      setCartCount(out?.cart_quantity ?? 0);

      row.remove();
      const any = !!itemsWrap.querySelector(".softadastra-cart-item");
      if (btnClear) btnClear.disabled = !any;
      if (btnCheckout) btnCheckout.disabled = !any;
      if (!any)
        itemsWrap.innerHTML =
          '<div class="softadastra-empty">Your cart is empty.</div>';
    } catch (err) {
      alert(err.message || "Remove error");
    } finally {
      row.style.opacity = "";
    }
  }

  async function handleClear() {
    if (!confirm("Clear the entire cart?")) return;
    try {
      const out = await post("/cart/clear", {});
      itemsWrap.innerHTML =
        '<div class="softadastra-empty">Your cart is empty.</div>';

      // totaux page
      sumQtyEl.textContent = "0";
      sumSubEl.textContent = "0.00";

      // ✅ badge header
      setCartCount(out?.cart_quantity ?? 0); // sera 0

      if (btnClear) btnClear.disabled = true;
      if (btnCheckout) btnCheckout.disabled = true;
    } catch (err) {
      alert(err.message || "Clear error");
    }
  }

  itemsWrap.addEventListener("click", (e) => {
    const row = e.target.closest(".softadastra-cart-item");
    if (!row) return;
    if (e.target.matches(".softadastra-btn-update")) handleUpdate(row);
    if (e.target.matches(".softadastra-btn-remove")) handleRemove(row);
  });

  btnClear?.addEventListener("click", handleClear);
  btnRefresh?.addEventListener("click", () => location.reload());
  btnCheckout?.addEventListener("click", () => {
    location.href = "/checkout";
  });
})();
