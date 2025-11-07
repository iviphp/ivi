// ivi.php – welcome page interactions
document.addEventListener("DOMContentLoaded", () => {
  // year
  const y = document.getElementById("y");
  if (y) y.textContent = new Date().getFullYear();

  // copy buttons
  document.querySelectorAll(".copy").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const sel = btn.getAttribute("data-copy");
      const el = sel ? document.querySelector(sel) : null;
      const txt = el ? (el.textContent || "").trim() : "";
      try {
        await navigator.clipboard.writeText(txt);
        btn.textContent = "Copied ✓";
      } catch {
        btn.textContent = "Unable to copy";
      }
      setTimeout(() => (btn.textContent = "Copy"), 1200);
    });
  });

  // sticky header shadow on scroll
  const header = document.querySelector("[data-header]");
  if (header) {
    const onScroll = () => {
      const scrolled = window.scrollY > 4;
      header.classList.toggle("is-scrolled", scrolled);
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }
});
