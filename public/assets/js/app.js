// ivi.php – global boot
document.addEventListener("DOMContentLoaded", () => {
  const y = document.getElementById("y");
  if (y) y.textContent = new Date().getFullYear();

  const header = document.querySelector("[data-header]");
  if (header) {
    const onScroll = () =>
      header.classList.toggle("is-scrolled", window.scrollY > 4);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }
});
document.addEventListener("spa:page:init", (e) => {
  console.log("[spa:page:init]", e.detail);
});

(function initThemeToggle() {
  const STORAGE_KEY = "ivi_theme"; // "light" | "dark" | null
  const root = document.documentElement;

  function getSystemPref() {
    return window.matchMedia &&
      window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  }

  function getSaved() {
    try {
      return localStorage.getItem(STORAGE_KEY);
    } catch {
      return null;
    }
  }

  function setSaved(v) {
    try {
      localStorage.setItem(STORAGE_KEY, v);
    } catch {}
  }

  function applyTheme(theme) {
    if (theme === "dark") root.classList.add("dark");
    else root.classList.remove("dark");
    updateIcon(theme);
  }

  function updateIcon(theme) {
    const icon = document.querySelector("[data-theme-icon]");
    if (!icon) return;
    // FontAwesome: moon for light-mode (switch to dark), sun for dark-mode (switch to light)
    if (theme === "dark") {
      icon.classList.remove("fa-moon");
      icon.classList.add("fa-sun");
    } else {
      icon.classList.remove("fa-sun");
      icon.classList.add("fa-moon");
    }
  }

  function currentTheme() {
    return root.classList.contains("dark") ? "dark" : "light";
  }

  // 1) Initial theme
  const saved = getSaved();
  const initial =
    saved === "dark" || saved === "light" ? saved : getSystemPref();
  applyTheme(initial);

  // 2) Button handler
  document.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-theme-toggle]");
    if (!btn) return;

    const next = currentTheme() === "dark" ? "light" : "dark";
    applyTheme(next);
    setSaved(next);
  });

  // 3) If user didn't choose explicitly, follow system changes
  if (!saved && window.matchMedia) {
    const mq = window.matchMedia("(prefers-color-scheme: dark)");
    mq.addEventListener?.("change", () => applyTheme(getSystemPref()));
  }
})();
