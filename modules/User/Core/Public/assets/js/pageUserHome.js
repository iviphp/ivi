// ---------------------------
// Page spécifique : UserHome
// ---------------------------
window.pageUserHome = {
  init() {
    console.log("UserHome page init");
    loadUser();
  },
};

// ---------------------------
// Page globale : Home (accueil)
// ---------------------------
window.pageHome = {
  init() {
    console.log("Home page initialized");
  },
};

// ---------------------------
// Fonction pour récupérer l'utilisateur
// ---------------------------
async function loadUser() {
  try {
    const res = await fetch("/api/user/me", {
      headers: { Accept: "application/json" },
      credentials: "include",
    });

    const data = await res.json();
    const container = document.getElementById("user-info-content");

    if (!data.success) {
      container.innerHTML = `<p>User not authenticated.</p>`;
      return;
    }

    const user = data.user;

    document.getElementById("welcome-title").textContent =
      "Welcome " + (user.username ?? "User");

    container.innerHTML = `
      <ul>
        <li><strong>ID:</strong> ${user.id}</li>
        <li><strong>Username:</strong> ${user.username}</li>
        <li><strong>Email:</strong> ${user.email}</li>
        <li><strong>Roles:</strong> ${user.roles.join(", ")}</li>
      </ul>
    `;
  } catch (err) {
    console.error("Failed to load user", err);
    document.getElementById(
      "user-info-content"
    ).innerHTML = `<p>Error loading user data.</p>`;
  }
}

// ---------------------------
// Auto-init page sur événement SPA
// ---------------------------
function autoInitPage(e) {
  // URL du fragment SPA ou page actuelle
  const url = e?.detail?.url || window.location.pathname;

  // Générer le nom JS de la page : /user/home → pageUserHome
  const name =
    "page" +
    url
      .replace(/\?.*$/, "")
      .replace(/^\/+|\/+$/g, "")
      .split("/")
      .map((x) => x.charAt(0).toUpperCase() + x.slice(1))
      .join("");

  if (window[name]?.init) {
    console.log("[SPA] Lazy init →", name);
    window[name].init();
  } else {
    // Retry limité si script n'est pas encore chargé
    let attempts = 0;
    const maxAttempts = 20;
    const retry = () => {
      if (window[name]?.init) {
        console.log("[SPA] Lazy init after load →", name);
        window[name].init();
      } else if (attempts++ < maxAttempts) {
        setTimeout(retry, 50);
      } else {
        console.warn(`[SPA] Page init not found for ${name}`);
      }
    };
    retry();
  }
}

// 1) Écouter les futurs événements SPA
document.addEventListener("spa:page-ready", autoInitPage);

// 2) Auto-init immédiate si le fragment est déjà présent (premier chargement)
if (document.getElementById("user-info-content")) {
  autoInitPage();
}
