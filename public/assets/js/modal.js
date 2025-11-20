/* === Popup / Modal util === */
/* API:
   showMessage(type, {
     text, module, linkHref, onSuccess,
     autoCloseMs,           // number | undefined (default: success=3000, error/loading=0)
     closeOnBackdrop,       // boolean | undefined (default: error=false, else=true)
     closeOnSwipe           // boolean | undefined (default: touchDevice? true : false)
   })
   closePopup()
*/

let shopMessageCallback = null;

function ensurePopupDom() {
  let popup = document.getElementById("shop-popup");
  if (popup) return popup;

  // Inject structure if missing
  const tpl = document.createElement("div");
  tpl.innerHTML = `
  <div id="shop-popup" class="shop-popup" style="display:none;">
    <div class="shop-popup-backdrop"></div>
    <div class="shop-popup-sheet" role="dialog" aria-live="polite" aria-modal="true">
      <div class="shop-popup-grabber" aria-hidden="true"></div>
      <div class="shop-popup-header">
        <span id="popup-icon" class="popup-icon" aria-hidden="true"></span>
        <h3 id="popup-title" class="popup-title">Message</h3>
      </div>
      <div class="shop-popup-body">
        <p id="popup-text" class="popup-text"></p>
        <a id="popup-link" class="popup-link" href="#" style="display:none;">Open</a>
      </div>
      <button type="button" class="shop-popup-close" aria-label="Close">&times;</button>
    </div>
  </div>`;
  document.body.appendChild(tpl.firstElementChild);
  return document.getElementById("shop-popup");
}

function lockBodyScroll() {
  // Prevent background scroll (desktop + mobile)
  document.documentElement.dataset._prevOverflow =
    document.documentElement.style.overflow || "";
  document.body.dataset._prevOverflow = document.body.style.overflow || "";
  document.documentElement.style.overflow = "hidden";
  document.body.style.overflow = "hidden";
}

function unlockBodyScroll() {
  document.documentElement.style.overflow =
    document.documentElement.dataset._prevOverflow || "";
  document.body.style.overflow = document.body.dataset._prevOverflow || "";
  delete document.documentElement.dataset._prevOverflow;
  delete document.body.dataset._prevOverflow;
}

function showMessage(type, options = {}) {
  const popup = ensurePopupDom();
  popup.classList.add("show"); // active la flex + pointer-events
  requestAnimationFrame(() => {
    popup.querySelector(".shop-popup-sheet").style.transform = ""; // recalcul
  });

  const icon = document.getElementById("popup-icon");
  const title = document.getElementById("popup-title");
  const msg = document.getElementById("popup-text");
  const link = document.getElementById("popup-link");
  const sheet = popup.querySelector(".shop-popup-sheet");
  const backdrop = popup.querySelector(".shop-popup-backdrop");
  const btnClose = popup.querySelector(".shop-popup-close");

  const mainContent =
    document.getElementById("shop-panel") ||
    document.querySelector(".sa-card__body");
  if (mainContent) mainContent.classList.add("blur-background");

  // Options with sensible defaults
  let {
    text,
    module = "",
    linkHref = "",
    onSuccess = null,
    autoCloseMs, // success: 3000; error/loading: 0
    closeOnBackdrop, // error: false; others: true
    closeOnSwipe, // default: touchDevice ? true : false
    lockScroll, // NEW: si false, on ne verrouille pas le scroll
    showBackdrop, // NEW: si false, on cache le backdrop
  } = options;

  // Defaults intelligents
  const isLoading = type === "loading";
  if (typeof lockScroll !== "boolean") lockScroll = !isLoading; // loading => pas de lock
  if (typeof showBackdrop !== "boolean") showBackdrop = !isLoading; // loading => pas de backdrop

  if (!text || typeof text !== "string") {
    text =
      type === "success"
        ? "Great! Everything went through smoothly."
        : type === "error"
        ? "Oops! Something went wrong. Please try again."
        : "Hang tight, we're working on it...";
  }

  const iconSuccess = `
<svg class="success-icon" fill="none" viewBox="0 0 24 24">
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
</svg>`;
  const iconError = `
<svg fill="none" viewBox="0 0 24 24" stroke="#c62828">
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
</svg>`;
  const iconLoading = `
<svg xmlns="http://www.w3.org/2000/svg" class="spin" fill="none" viewBox="0 0 24 24" stroke="#999">
  <circle cx="12" cy="12" r="10" stroke-width="4" stroke-opacity="0.3" />
  <path d="M12 2a10 10 0 0110 10" stroke-width="4" stroke-linecap="round" />
</svg>`;

  icon.innerHTML =
    type === "success"
      ? iconSuccess
      : type === "error"
      ? iconError
      : iconLoading;

  title.innerHTML =
    type === "success"
      ? '<i class="fas fa-check-circle" style="color:#16a34a;"></i> Well done! Success!'
      : type === "error"
      ? '<i class="fas fa-times-circle" style="color:#c62828;"></i> Uh-oh! Something went wrong'
      : '<i class="fas fa-spinner fa-spin" style="color:#999;"></i> Please wait, working on it...';

  msg.textContent = module ? `[${module}] ${text}` : text;

  if (linkHref && type === "success") {
    link.href = linkHref;
    link.style.display = "inline-block";
  } else {
    link.removeAttribute("href");
    link.style.display = "none";
  }

  // Show as flex to keep centering
  popup.style.display = "flex";
  unlockBodyScroll();
  if (lockScroll) lockBodyScroll();

  backdrop.style.display = showBackdrop ? "" : "none";

  /* ===== Closing rules ===== */
  const isTouchDevice =
    "ontouchstart" in window || navigator.maxTouchPoints > 0;
  const allowBackdrop =
    typeof closeOnBackdrop === "boolean" ? closeOnBackdrop : type !== "error";
  const allowSwipe =
    typeof closeOnSwipe === "boolean" ? closeOnSwipe : isTouchDevice;

  // Clear previous timers/listeners
  window.clearTimeout(popup._hideTimer);

  // Remove previously attached handlers to avoid duplicates
  if (popup._containerClickHandler) {
    popup.removeEventListener("click", popup._containerClickHandler, true);
    popup._containerClickHandler = null;
  }
  if (popup._btnCloseHandler) {
    btnClose?.removeEventListener("click", popup._btnCloseHandler);
    popup._btnCloseHandler = null;
  }
  // Clean previous swipe handlers
  if (sheet) {
    if (sheet._touchBound) {
      sheet.removeEventListener("touchstart", sheet._touchStart);
      sheet.removeEventListener("touchmove", sheet._touchMove);
      sheet.removeEventListener("touchend", sheet._touchEnd);
      sheet._touchBound = false;
    }
    if (sheet._mouseBound) {
      sheet.removeEventListener("mousedown", sheet._mouseStart);
      document.removeEventListener("mousemove", sheet._mouseMove);
      document.removeEventListener("mouseup", sheet._mouseUp);
      sheet._mouseBound = false;
    }
  }

  // (1) Backdrop: close only if click target IS the backdrop (not inside sheet)
  if (allowBackdrop) {
    popup._containerClickHandler = function (e) {
      const sheet = popup.querySelector(".shop-popup-sheet");
      if (!sheet.contains(e.target)) {
        closePopup();
      }
    };
    popup.addEventListener("click", popup._containerClickHandler, true);
  }

  // (2) Close button: always enabled
  if (btnClose) {
    popup._btnCloseHandler = () => closePopup();
    btnClose.addEventListener("click", popup._btnCloseHandler);
  }

  // (3) Swipe-to-close: touch devices by default (desktop off unless forced)
  if (sheet && allowSwipe) {
    let dragging = false,
      startY = 0,
      currentY = 0;

    // Touch
    sheet._touchStart = (e) => {
      dragging = true;
      startY = e.touches[0].clientY;
      sheet.style.transition = "none";
    };
    sheet._touchMove = (e) => {
      if (!dragging) return;
      currentY = e.touches[0].clientY;
      const d = currentY - startY;
      if (d > 0) sheet.style.transform = `translateY(${d}px)`;
    };
    sheet._touchEnd = () => {
      if (!dragging) return;
      dragging = false;
      const d = currentY - startY;
      sheet.style.transition = "transform .3s ease";
      if (d > 100) closePopup();
      else sheet.style.transform = "translateY(0)";
    };
    sheet.addEventListener("touchstart", sheet._touchStart, {
      passive: true,
    });
    sheet.addEventListener("touchmove", sheet._touchMove, { passive: true });
    sheet.addEventListener("touchend", sheet._touchEnd, { passive: true });
    sheet._touchBound = true;

    // Desktop swipe only if explicitly allowed
    if (!isTouchDevice && allowSwipe === true) {
      sheet._mouseStart = (e) => {
        dragging = true;
        startY = e.clientY;
        sheet.style.transition = "none";
      };
      sheet._mouseMove = (e) => {
        if (!dragging) return;
        currentY = e.clientY;
        const d = currentY - startY;
        if (d > 0) sheet.style.transform = `translateY(${d}px)`;
      };
      sheet._mouseUp = () => {
        if (!dragging) return;
        dragging = false;
        const d = currentY - startY;
        sheet.style.transition = "transform .3s ease";
        if (d > 100) closePopup();
        else sheet.style.transform = "translateY(0)";
        document.removeEventListener("mousemove", sheet._mouseMove);
        document.removeEventListener("mouseup", sheet._mouseUp);
      };
      sheet.addEventListener("mousedown", sheet._mouseStart);
      document.addEventListener("mousemove", sheet._mouseMove);
      document.addEventListener("mouseup", sheet._mouseUp);
      sheet._mouseBound = true;
    }
  }

  // (4) Auto-close: success=3s by default; error/loading=never
  const defaultMs = type === "success" ? 3000 : 0;
  const ms = typeof autoCloseMs === "number" ? autoCloseMs : defaultMs;
  if (ms > 0) {
    popup._hideTimer = window.setTimeout(() => closePopup(), ms);
  } else {
    popup._hideTimer = null;
  }

  // (5) Success callback (e.g. redirect)
  shopMessageCallback =
    type === "success" && typeof onSuccess === "function" ? onSuccess : null;
}

function closePopup() {
  const popup = document.getElementById("shop-popup");
  if (!popup) return;
  popup.classList.remove("show");

  window.clearTimeout(popup._hideTimer);
  popup._hideTimer = null;

  // Reset transform pour sheet
  const sheet = popup.querySelector(".shop-popup-sheet");
  if (sheet) {
    sheet.style.transition = "transform 0.3s ease";
    sheet.style.transform = "translateY(0)";
  }

  popup.style.display = "none";
  unlockBodyScroll();

  const mainContent =
    document.getElementById("shop-panel") ||
    document.querySelector(".sa-card__body");
  if (mainContent) mainContent.classList.remove("blur-background");

  if (typeof shopMessageCallback === "function") {
    const fn = shopMessageCallback;
    shopMessageCallback = null;
    try {
      fn();
    } catch {}
  }
}
