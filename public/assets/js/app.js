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
