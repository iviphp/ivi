/* ===== Micro-UX: ombre quand on scrolle horizontalement le tableau ===== */
(() => {
  const wrap = document.getElementById("cmd-ordersWrap");
  if (!wrap) return;
  const onScroll = () => {
    wrap.classList.toggle(
      "cmd-is-scrolling",
      wrap.scrollLeft > 0 || wrap.scrollTop > 0
    );
  };
  wrap.addEventListener("scroll", onScroll, {
    passive: true,
  });
})();

async function fetchTotalForRole(r) {
  const url = new URL("/orders/list", location.origin);
  url.searchParams.set("role", r);
  url.searchParams.set("per_page", 1); // tiny payload, we just need 'total'
  const res = await fetch(url.toString(), {
    headers: { Accept: "application/json" },
  });
  if (!res.ok) return 0;
  const j = await res.json().catch(() => ({}));
  return Number(j.total || 0);
}

async function autoPickRoleIfNeeded() {
  // If the URL explicitly sets a tab, don't override
  const roleFromUrl = new URLSearchParams(location.search).get("role");
  if (roleFromUrl === "buyer" || roleFromUrl === "seller") {
    role = roleFromUrl; // override defaultRole
    return;
  }
  try {
    const [buyerTotal, sellerTotal] = await Promise.all([
      fetchTotalForRole("buyer"),
      fetchTotalForRole("seller"),
    ]);
    role = sellerTotal > buyerTotal ? "seller" : "buyer";
  } catch {
    // fallback: keep current role
  }
}

(function () {
  const tabs = document.getElementById("cmd-tabs");
  const tbody = document.getElementById("cmd-tbody");
  const pager = document.getElementById("cmd-pager");
  const qEl = document.getElementById("cmd-q");
  const stEl = document.getElementById("cmd-status");
  const btnF = document.getElementById("cmd-btnSearch");
  const btnR = document.getElementById("cmd-btnReset");

  let role = defaultRole === "seller" ? "seller" : "buyer";
  let page = defaultPage;
  let q = "";
  let st = defaultStatus;

  function tableColspan() {
    const theadRow = document.querySelector("#cmd-tbl thead tr");
    return theadRow ? theadRow.children.length : 6;
  }

  function setActiveTab() {
    [...tabs.querySelectorAll(".cmd-tab")].forEach((b) => {
      b.classList.toggle("active", b.dataset.role === role);
    });
  }

  tabs.addEventListener("click", (e) => {
    const btn = e.target.closest(".cmd-tab");
    if (!btn) return;
    const newRole = btn.dataset.role;
    if (newRole && newRole !== role) {
      role = newRole;
      page = 1;
      fetchList();
      setActiveTab();
    }
  });

  btnF.onclick = () => {
    q = qEl.value.trim();
    st = stEl.value;
    page = 1;
    fetchList();
  };
  btnR.onclick = () => {
    q = "";
    st = "";
    qEl.value = "";
    stEl.value = "";
    page = 1;
    fetchList();
  };

  function fmtMoney(n, cur) {
    return (cur || "$") + Number(n || 0).toFixed(2);
  }

  function pick(obj, keys, fallback = "") {
    for (const k of keys) {
      const v = obj?.[k];
      if (v !== undefined && v !== null && String(v).trim() !== "") return v;
    }
    return fallback;
  }

  function trackingCodeOf(it) {
    return pick(
      it,
      [
        "tracking_no",
        "trackingNo",
        "tracking",
        "shipment_tracking",
        "tracking_code",
        "trackingCode",
      ],
      ""
    );
  }

  // passé au rendu: badgeInfo(it, role)
  function badgeInfo(it, role) {
    const pick = (obj, keys, fallback = "") => {
      for (const k of keys) {
        const v = obj?.[k];
        if (v != null && String(v).trim() !== "") return v;
      }
      return fallback;
    };
    let pay = String(
      pick(it, ["payment_status", "paymentStatus", "pay_status"], "")
    ).toLowerCase();
    const st = String(
      pick(it, ["status", "order_status", "orderStatus"], "")
    ).toLowerCase();

    // normalisation paiement
    if (!pay || pay === "unpaid") {
      if (["processing", "shipped", "delivered", "completed"].includes(st))
        pay = "paid";
      else if (st === "awaiting_payment") pay = "pending";
    }
    if (pay === "succeeded") pay = "paid";

    const mapTxtPay = {
      unpaid: "Unpaid",
      partial: "Partial",
      paid: "Paid",
      refunded: "Refunded",
      pending: "Pending",
      failed: "Failed",
      canceled: "Canceled",
    };
    const mapClsPay = {
      unpaid: "cmd-badge-pay-unpaid",
      partial: "cmd-badge-pay-partial",
      paid: "cmd-badge-pay-paid",
      refunded: "cmd-badge-pay-refunded",
      pending: "cmd-badge-pay-pending",
      failed: "cmd-badge-pay-failed",
      canceled: "cmd-badge-pay-canceled",
    };

    const mapTxtSt = {
      pending: "Pending",
      awaiting_payment: "Awaiting Payment",
      paid: "Paid",
      processing: "Processing",
      shipped: "Shipped",
      delivered: "Delivered",
      completed: "Completed",
      canceled: "Canceled",
      refunded: "Refunded",
    };
    const mapClsSt = {
      pending: "cmd-badge-pending",
      awaiting_payment: "cmd-badge-awaiting_payment",
      paid: "cmd-badge-paid",
      processing: "cmd-badge-processing",
      shipped: "cmd-badge-shipped",
      delivered: "cmd-badge-delivered", // 👈 garder ceci
      completed: "cmd-badge-completed",
      canceled: "cmd-badge-canceled",
      refunded: "cmd-badge-refunded",
    };

    if (role === "seller") {
      if (st)
        return {
          text: mapTxtSt[st] || st,
          cls: mapClsSt[st] || "",
        };
      if (pay)
        return {
          text: mapTxtPay[pay] || pay,
          cls: mapClsPay[pay] || "",
        };
      return {
        text: "-",
        cls: "",
      };
    } else {
      // 👇 NEW: si le statut est logistique, on affiche le statut (et donc "Delivered")
      if (["processing", "shipped", "delivered", "completed"].includes(st)) {
        return {
          text: mapTxtSt[st] || st,
          cls: mapClsSt[st] || "",
        };
      }
      // sinon on garde le paiement
      if (pay)
        return {
          text: mapTxtPay[pay] || pay,
          cls: mapClsPay[pay] || "",
        };
      return {
        text: mapTxtSt[st] || st || "-",
        cls: mapClsSt[st] || "",
      };
    }
  }

  function badgesHtml(it) {
    const pay = String(
      pick(it, ["payment_status", "paymentStatus", "pay_status"], "")
    ).toLowerCase();
    const st = String(
      pick(it, ["status", "order_status", "orderStatus"], "")
    ).toLowerCase();

    const mapTxtPay = {
      unpaid: "Unpaid",
      partial: "Partial",
      paid: "Paid",
      refunded: "Refunded",
      pending: "Pending",
      succeeded: "Paid",
      failed: "Failed",
      canceled: "Canceled",
    };
    const mapClsPay = {
      unpaid: "cmd-badge-pay-unpaid",
      partial: "cmd-badge-pay-partial",
      paid: "cmd-badge-pay-paid",
      refunded: "cmd-badge-pay-refunded",
      pending: "cmd-badge-pay-pending",
      succeeded: "cmd-badge-pay-paid",
      failed: "cmd-badge-pay-failed",
      canceled: "cmd-badge-pay-canceled",
    };

    const mapTxtSt = {
      pending: "Pending",
      awaiting_payment: "Awaiting Payment",
      paid: "Paid",
      processing: "Processing",
      shipped: "Shipped",
      delivered: "Delivered",
      completed: "Completed",
      canceled: "Canceled",
      refunded: "Refunded",
    };
    const mapClsSt = {
      pending: "cmd-badge-pending",
      awaiting_payment: "cmd-badge-awaiting_payment",
      paid: "cmd-badge-paid",
      processing: "cmd-badge-processing",
      shipped: "cmd-badge-shipped",
      delivered: "cmd-badge-paid",
      completed: "cmd-badge-completed",
      canceled: "cmd-badge-canceled",
      refunded: "cmd-badge-refunded",
    };

    const orderBadge = st
      ? `<span class="cmd-badge ${mapClsSt[st] || ""}">${
          mapTxtSt[st] || st
        }</span>`
      : "";
    const payBadge = pay
      ? `<span class="cmd-badge ${mapClsPay[pay] || ""} cmd-badge-ghost">${
          mapTxtPay[pay] || pay
        }</span>`
      : "";
    return {
      desktop: orderBadge + payBadge,
      mobile: `<span class="cmd-show-sm">${orderBadge}${payBadge}</span>`,
    };
  }

  function renderRows(items) {
    if (!items || !items.length) {
      // état vide en dehors du tableau
      tbody.innerHTML = "";
      showEmptyPanel();
      return;
    }

    // on a des items → montre le tableau et cache le panneau vide
    hideEmptyPanel();

    // helper local: code de suivi (plusieurs clés tolérées)
    const trackingCodeOf = (it) =>
      pick(
        it,
        [
          "tracking_no",
          "trackingNo",
          "tracking",
          "shipment_tracking",
          "tracking_code",
          "trackingCode",
        ],
        ""
      );

    tbody.innerHTML = items
      .map((it) => {
        const img = it.first_item_image
          ? `<img src="${it.first_item_image}" alt="" class="cmd-img">`
          : `<div class="cmd-img"></div>`;

        const buyer = it.buyer_name
          ? `<div class="cmd-hide-sm cmd-meta">Buyer: ${it.buyer_name}</div>`
          : "";

        const created = (it.created_at || "").replace("T", " ").slice(0, 16);

        const { text: badgeText, cls: badgeCls } = badgeInfo(it, role);
        const badgesDesktop = `<span class="cmd-badge ${badgeCls}">${badgeText}</span>`;
        const badgesMobile = `<span class="cmd-badge ${badgeCls} cmd-show-sm">${badgeText}</span>`;

        const dateMobile = created
          ? `<div class="cmd-meta cmd-show-sm">Date: ${created}</div>`
          : "";

        const currencySym = it.currency === "USD" ? "$" : it.currency || "$";
        const totalHtml = `<strong>${fmtMoney(
          it.total_amount,
          currencySym
        )}</strong>`;

        const tcode = trackingCodeOf(it);
        const trackHref = tcode
          ? `/show/shipment/${encodeURIComponent(tcode)}`
          : `/track/${encodeURIComponent(tcode)}`;

        return `
  <tr>
    <!-- Order -->
    <td class="cmd-col-order">
      <div class="cmd-row" style="align-items:flex-start;">
        ${img}
        <div>
          <div class="cmd-stack">
            <strong>${it.order_number}</strong>
            ${badgesMobile}
          </div>
          <div class="cmd-meta">
            ${it.currency || "USD"} • ${it.items_count || 0} item(s)
          </div>
          ${dateMobile}
          ${buyer}
        </div>
      </div>
    </td>

    <!-- Items (desktop) -->
    <td class="cmd-hide-sm" style="text-align:center;">${
      it.items_count || 0
    }</td>

    <!-- Total -->
    <td class="cmd-nowrap">${totalHtml}</td>

    <!-- Date (desktop) -->
    <td class="cmd-hide-sm cmd-nowrap">${created}</td>

    <!-- Status (desktop) -->
    <td class="cmd-hide-sm cmd-nowrap">
      <div class="cmd-status">${badgesDesktop}</div>
    </td>

    <!-- Action -->
      <td class="cmd-nowrap cmd-td-actions">
      <div class="cmd-actions">
        <a class="cmd-btn cmd-white" href="/order/confirmation?no=${encodeURIComponent(
          it.order_number
        )}">Details</a>
        <a class="cmd-btn cmd-btn-track" href="${trackHref}">
          <svg class="cmd-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 7h13v10H3zM16 10h4l1 3v4h-5zM6 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4z" fill="currentColor"/>
          </svg>
          Track
        </a>
      </div>
    </td>
  </tr>`;
      })
      .join("");
  }

  // crée ou récupère un panneau "empty state" à côté du tableau
  const wrap = document.getElementById("cmd-ordersWrap");
  let emptyPanel = document.getElementById("cmd-empty-panel");
  if (!emptyPanel) {
    emptyPanel = document.createElement("div");
    emptyPanel.id = "cmd-empty-panel";
    emptyPanel.style.display = "none";
    emptyPanel.setAttribute("aria-live", "polite");
    wrap.parentNode.insertBefore(emptyPanel, wrap.nextSibling);
  }

  function showEmptyPanel() {
    wrap.style.display = "none";
    emptyPanel.style.display = "";
    emptyPanel.innerHTML = `
    <div class="cmd-empty">
      <svg class="cmd-empty-icon" viewBox="0 0 24 24" width="56" height="56" aria-hidden="true">
        <rect x="3" y="7" width="18" height="13" rx="2"></rect>
        <path d="M3 10h18M7 3h10v4H7zM8.5 14h7M8.5 17h5"></path>
      </svg>
      <div class="cmd-empty-title">No orders found</div>
      <div class="cmd-empty-sub">Try adjusting your filters or search query.</div>
      <button type="button" class="cmd-btn cmd-white" id="cmd-empty-reset">Reset filters</button>
    </div>
  `;
    const resetBtn = document.getElementById("cmd-empty-reset");
    if (resetBtn) {
      resetBtn.addEventListener("click", () => {
        q = "";
        st = "";
        qEl.value = "";
        stEl.value = "";
        page = 1;
        fetchList();
      });
    }
  }

  function hideEmptyPanel() {
    emptyPanel.style.display = "none";
    wrap.style.display = ""; // re-affiche le tableau
  }

  function renderPager(page, pages) {
    if (pages <= 1) {
      pager.innerHTML = "";
      return;
    }
    const prevDisabled = page <= 1 ? "disabled" : "";
    const nextDisabled = page >= pages ? "disabled" : "";
    pager.innerHTML = `
        <button ${prevDisabled} data-p="${page - 1}">Prev</button>
        <span style="align-self:center;padding:6px 8px;">Page ${page} of ${pages}</span>
        <button ${nextDisabled} data-p="${page + 1}">Next</button>
    `;
  }

  pager.addEventListener("click", (e) => {
    const b = e.target.closest("button[data-p]");
    if (!b || b.disabled) return;
    const p = Number(b.dataset.p);
    if (p > 0) {
      page = p;
      fetchList();
    }
  });

  async function fetchList() {
    tbody.innerHTML = `
        <tr>
          <td colspan="${tableColspan()}" style="text-align:center;padding:20px;">
            <span class="cmd-spinner"></span>
          </td>
        </tr>
    `;
    try {
      const url = new URL("/orders/list", location.origin);
      url.searchParams.set("role", role);
      if (st) url.searchParams.set("status", st);
      if (q) url.searchParams.set("q", q);
      url.searchParams.set("page", page);
      url.searchParams.set("per_page", 10);

      const res = await fetch(url.toString(), {
        headers: {
          Accept: "application/json",
        },
      });
      const j = await res.json();
      renderRows(j.items || []);
      renderPager(j.page || 1, j.pages || 1);
    } catch (err) {
      tbody.innerHTML = `
          <tr>
            <td colspan="${tableColspan()}" style="text-align:center;padding:20px;color:#e5484d;">
              Loading error
            </td>
          </tr>
        `;
    }
  }

  // init
  stEl.value = st;

  (async () => {
    // show spinner while we decide which tab to open
    tbody.innerHTML = `
    <tr>
      <td colspan="${tableColspan()}" style="text-align:center;padding:20px;">
        <span class="cmd-spinner"></span>
      </td>
    </tr>
  `;

    await autoPickRoleIfNeeded(); // decide best tab by totals
    setActiveTab(); // reflect chosen role visually
    fetchList(); // load table for that role
  })();
})();
