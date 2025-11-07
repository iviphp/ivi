document.addEventListener("DOMContentLoaded", () => {
  // dynamic year
  document.getElementById("y").textContent = new Date().getFullYear();

  // highlight current link in navbar
  const path = window.location.pathname;
  document.querySelectorAll(".nav-links a").forEach((a) => {
    if (a.getAttribute("href") === path) a.classList.add("active");
  });

  // simple scroll reveal (optional)
  const sections = document.querySelectorAll(".docs-section");
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting) e.target.classList.add("visible");
      });
    },
    { threshold: 0.15 }
  );
  sections.forEach((s) => observer.observe(s));
});
