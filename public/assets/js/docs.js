document.addEventListener("DOMContentLoaded", () => {
  // Année
  const y = document.getElementById("y");
  if (y) y.textContent = new Date().getFullYear();

  // Highlight.js si présent
  if (window.hljs) {
    document.querySelectorAll(".code-body pre code").forEach((el) => {
      window.hljs.highlightElement(el);
    });
  }

  // Boutons Copy
  document.querySelectorAll(".code-copy").forEach((btn) => {
    btn.addEventListener("click", async () => {
      try {
        // 1) Priorité au data-raw EXACT (aucun retour à la ligne ajouté par le HTML)
        const raw = btn.getAttribute("data-raw");
        let textToCopy = raw ?? "";

        // 2) Sinon, on lit le contenu visible (innerText préserve l'espacement)
        if (!textToCopy) {
          const code = btn.closest(".code")?.querySelector(".code-body pre");
          textToCopy = code ? code.innerText : "";
        }

        // 3) Normalisation optionnelle:
        //    - pour les commandes shell, tu veux souvent "une seule ligne".
        //    Dé-commente la ligne suivante si tu veux écraser les retours:
        // textToCopy = textToCopy.replace(/\s*\n\s*/g, " ").trim();

        await navigator.clipboard.writeText(textToCopy);
        btn.classList.add("copied");
        setTimeout(() => btn.classList.remove("copied"), 1600);
      } catch (err) {
        console.error("Copy failed:", err);
        btn.textContent = "Copy failed";
        setTimeout(() => (btn.textContent = "Copy"), 1200);
      }
    });
  });
});
(function () {
  // Util : récupère "php" depuis "language-php" / "lang-php" / data-lang
  function detectLang(codeEl) {
    const cls = codeEl.className || "";
    const m1 = cls.match(/language-([a-z0-9+\-]+)/i);
    const m2 = cls.match(/lang-([a-z0-9+\-]+)/i);
    if (m1) return m1[1];
    if (m2) return m2[1];
    const dl = codeEl.getAttribute("data-lang");
    if (dl) return dl;
    // heuristique simple
    const text = codeEl.textContent.trim();
    if (text.startsWith("<?php")) return "php";
    if (text.startsWith("{") || text.startsWith("[")) return "json";
    if (/^\s*(GET|POST|PUT|DELETE|PATCH)\s+\/.*/.test(text)) return "http";
    return "text";
  }

  // Ajoute .line si le highlighter ne l’a pas déjà fait
  function ensureLines(codeEl) {
    if (codeEl.querySelector(".line")) return;
    const raw = codeEl.textContent.replace(/\r\n/g, "\n");
    const frag = document.createDocumentFragment();
    raw.split("\n").forEach((ln, idx, arr) => {
      // évite une ligne vide finale additionnelle si le texte finit par "\n"
      if (idx === arr.length - 1 && ln === "") return;
      const span = document.createElement("span");
      span.className = "line";
      span.textContent = ln;
      frag.appendChild(span);
      if (idx < arr.length - 1) frag.appendChild(document.createTextNode("\n"));
    });
    codeEl.innerHTML = "";
    codeEl.appendChild(frag);
  }

  // Copie le texte sans numéros de ligne
  function getPlainCode(block) {
    const code = block.querySelector("code");
    if (!code) return "";
    // Si on a des .line, reconstruire proprement
    const lines = code.querySelectorAll(".line");
    if (lines.length) {
      return Array.from(lines)
        .map((l) => l.textContent)
        .join("\n");
    }
    return code.textContent;
  }

  function enhance(pre) {
    const code = pre.querySelector("code");
    if (!code) return;

    // Saut si déjà transformé
    if (pre.parentElement && pre.parentElement.classList.contains("code-block"))
      return;

    // Détection langue & header
    const lang = detectLang(code);
    const wrapper = document.createElement("div");
    wrapper.className = "code-block";

    const head = document.createElement("div");
    head.className = "code-head";

    const badge = document.createElement("div");
    badge.className = "lang-badge";
    badge.textContent = lang;

    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "copy-btn";
    btn.setAttribute("aria-label", "Copy code to clipboard");
    btn.textContent = "Copy";

    btn.addEventListener("click", async () => {
      try {
        await navigator.clipboard.writeText(getPlainCode(wrapper));
        btn.classList.add("copied");
        setTimeout(() => btn.classList.remove("copied"), 1400);
      } catch {
        // Fallback (sélection manuelle)
        const range = document.createRange();
        range.selectNodeContents(pre);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        try {
          document.execCommand("copy");
        } catch {}
        sel.removeAllRanges();
        btn.classList.add("copied");
        setTimeout(() => btn.classList.remove("copied"), 1400);
      }
    });

    head.appendChild(badge);
    head.appendChild(btn);

    // Injecter .line si manquant (améliore le zebra striping)
    ensureLines(code);

    // Wrap
    const parent = pre.parentElement;
    parent.insertBefore(wrapper, pre);
    wrapper.appendChild(head);
    wrapper.appendChild(pre);
  }

  function run() {
    // Ne touche que le contenu markdown
    const scope = document.querySelector(".markdown-body") || document;
    scope.querySelectorAll("pre").forEach(enhance);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", run);
  } else {
    run();
  }
})();
