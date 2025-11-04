// Récupération des infos utilisateur
fetch("/get-user", { credentials: "include" })
  .then((response) => {
    if (!response.ok) throw new Error("User fetch failed");
    return response.json();
  })
  .then((user) => {
    if (!user || !user.id) throw new Error("Utilisateur non valide");

    // Remplissage du nom et de l’image
    const userNameEl = document.getElementById("user-name");
    if (userNameEl) userNameEl.textContent = user.fullname;

    const profileImageEl = document.getElementById("profile-image");
    if (profileImageEl) profileImageEl.src = user.photo;

    // Badge messages
    const messageBadge = document.querySelector(".message-badge");
    if (messageBadge) {
      messageBadge.style.display =
        user.messageCount > 0 ? "inline-block" : "none";
      messageBadge.textContent = user.messageCount || "";
    }
  })
  .catch((error) => {
    console.error("❌ Erreur utilisateur :", error);
  });
