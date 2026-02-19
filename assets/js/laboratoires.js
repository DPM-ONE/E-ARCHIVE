/* ═══════════════════════════════════════════════════════════
   laboratoires.js — DPM Archive
   Vue cartes (par agence) + vue tableau avec pagination
   CRUD : ajout, édition, suppression via API
═══════════════════════════════════════════════════════════ */

/* ── État global ─────────────────────────────────────────── */
let filtered = [...ALL_DATA];
let currentPage = 1;
let perPage = 20;
let sortKey = "nom_laboratoire";
let sortDir = 1;
let currentView = "cards";
let pendingDeleteId = null;

/* ── Couleurs d'agence (rotation sur 4 couleurs) ─────────── */
function agenceBadge(agenceId, agenceLabel) {
  const colors = ["#1d4ed8", "#c2410c", "#15803d", "#7e22ce"];
  const bgs = ["#eff6ff", "#fff7ed", "#f0fdf4", "#fdf4ff"];
  const idx = (agenceId - 1) % 4;
  return `<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600;background:${bgs[idx]};color:${colors[idx]}">${agenceLabel || "—"}</span>`;
}

/* ── Helpers ─────────────────────────────────────────────── */
function initials(str) {
  return (
    (str || "?")
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((w) => w[0])
      .join("")
      .toUpperCase() || "?"
  );
}

/* ── Filtres ─────────────────────────────────────────────── */
function applyFilters() {
  const search = document
    .getElementById("searchInput")
    .value.toLowerCase()
    .trim();
  const agenceId = parseInt(document.getElementById("filterAgence").value) || 0;

  filtered = ALL_DATA.filter((l) => {
    if (search) {
      const hay = [l.nom_laboratoire, l.pays, l.agence_nom]
        .join(" ")
        .toLowerCase();
      if (!hay.includes(search)) return false;
    }
    if (agenceId && l.agence_id !== agenceId) return false;
    return true;
  });

  filtered.sort((a, b) => {
    let av = a[sortKey] || "",
      bv = b[sortKey] || "";
    if (typeof av === "string") {
      av = av.toLowerCase();
      bv = bv.toLowerCase();
    }
    if (av < bv) return -sortDir;
    if (av > bv) return sortDir;
    return 0;
  });

  currentPage = 1;
  document.getElementById("resultsCount").innerHTML =
    `<strong>${filtered.length}</strong> laboratoire${filtered.length !== 1 ? "s" : ""}`;

  if (currentView === "cards") renderCards();
  else {
    renderTable();
    renderPagination();
  }
}

function resetAll() {
  document.getElementById("searchInput").value = "";
  document.getElementById("filterAgence").value = "";
  applyFilters();
}

/* ── Vue ─────────────────────────────────────────────────── */
function setView(v) {
  currentView = v;
  document.getElementById("viewCards").style.display =
    v === "cards" ? "" : "none";
  document.getElementById("viewTable").style.display =
    v === "table" ? "" : "none";
  document
    .getElementById("btnViewCards")
    .classList.toggle("labo-view-btn--active", v === "cards");
  document
    .getElementById("btnViewTable")
    .classList.toggle("labo-view-btn--active", v === "table");
  applyFilters();
}

/* ── CARTES (groupées par agence) ────────────────────────── */
function renderCards() {
  const container = document.getElementById("viewCards");

  if (!filtered.length) {
    container.innerHTML = `<div class="labo-empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16"/><path d="M3 21h18"/>
            </svg>
            <h3>Aucun laboratoire trouvé</h3><p>Modifiez vos filtres ou ajoutez un nouveau laboratoire</p>
        </div>`;
    return;
  }

  /* Grouper par agence */
  const byAgence = {};
  filtered.forEach((l) => {
    const ak = l.agence_id || 0;
    if (!byAgence[ak])
      byAgence[ak] = { label: l.agence_nom || "Sans agence", labos: [] };
    byAgence[ak].labos.push(l);
  });

  /* Trier les agences par libellé */
  const agenceKeys = Object.keys(byAgence).sort((a, b) =>
    (byAgence[a].label || "").localeCompare(byAgence[b].label || ""),
  );

  let html = "";
  agenceKeys.forEach((ak) => {
    const { label, labos } = byAgence[ak];
    const totalAgence = labos.length;

    html += `<div class="labo-agence-block" id="ag_${ak}">
            <div class="labo-agence-header" onclick="toggleAgence('${ak}')">
                <svg class="labo-agence-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                <span class="labo-agence-name">${label}</span>
                <span class="labo-agence-count">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:11px;height:11px">
                        <path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16"/><path d="M3 21h18"/>
                    </svg>
                    ${totalAgence} labo${totalAgence > 1 ? "s" : ""}
                </span>
            </div>
            <div class="labo-agence-body" id="agbody_${ak}">
                <div class="labo-section">
                    <div class="labo-cards">`;

    labos.forEach((l) => {
      const ini = initials(l.nom_laboratoire);
      html += `<div class="labo-card" onclick="event.stopPropagation()">
                <div class="labo-card__initial-box">${ini}</div>
                <div class="labo-card__info">
                    <div class="labo-card__name">${l.nom_laboratoire}</div>
                    <div class="labo-card__pays">
                        ${l.pays ? `🌍 ${l.pays}` : '<span style="color:#94a3b8;font-style:italic">—</span>'}
                    </div>
                </div>
                <div class="labo-card__actions">
                    <button class="labo-action-btn" title="Modifier" onclick="openEditModal(${l.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <button class="labo-action-btn labo-action-btn--danger" title="Supprimer" onclick="openDelModal(${l.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                    </button>
                </div>
            </div>`;
    });

    html += `</div></div></div></div>`;
  });

  container.innerHTML = html;

  /* Ajuster la height des agence-body */
  agenceKeys.forEach((ak) => {
    const body = document.getElementById("agbody_" + ak);
    if (body) body.style.maxHeight = body.scrollHeight + "px";
  });
}

function toggleAgence(ak) {
  const block = document.getElementById("ag_" + ak);
  const body = document.getElementById("agbody_" + ak);
  if (!block || !body) return;
  const isCollapsed = block.classList.contains("collapsed");
  if (isCollapsed) {
    block.classList.remove("collapsed");
    body.style.maxHeight = body.scrollHeight + "px";
    body.style.opacity = "1";
  } else {
    block.classList.add("collapsed");
    body.style.maxHeight = "0";
    body.style.opacity = "0";
  }
}

/* ── TABLE AVEC PAGINATION ───────────────────────────────── */
function sortBy(key) {
  if (sortKey === key) {
    sortDir *= -1;
  } else {
    sortKey = key;
    sortDir = 1;
  }

  // Mise à jour des indicateurs de tri
  document.querySelectorAll(".labo-table thead th").forEach((th) => {
    th.classList.remove("sort-asc", "sort-desc");
  });

  const thMap = {
    nom_laboratoire: "th_nom",
    pays: "th_pays",
    agence_nom: "th_agence",
  };

  if (thMap[key]) {
    const th = document.getElementById(thMap[key]);
    if (th) th.classList.add(sortDir === 1 ? "sort-asc" : "sort-desc");
  }

  applyFilters();
}

function renderTable() {
  const tbody = document.getElementById("tableBody");
  const empty = document.getElementById("emptyState");
  const start = (currentPage - 1) * perPage;
  const page = filtered.slice(start, start + perPage);

  if (!filtered.length) {
    tbody.innerHTML = "";
    empty.style.display = "";
    return;
  }
  empty.style.display = "none";

  tbody.innerHTML = page
    .map((l) => {
      return `<tr>
            <td>
                <div class="labo-td-main">${l.nom_laboratoire}</div>
                <div class="labo-td-sub">${l.agence_nom || "—"}</div>
            </td>
            <td>${l.pays || '<span style="color:var(--labo-ink-soft);font-style:italic">—</span>'}</td>
            <td>${agenceBadge(l.agence_id, l.agence_nom)}</td>
            <td>
                <div class="labo-td-actions">
                    <button class="labo-action-btn" title="Modifier" onclick="openEditModal(${l.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <button class="labo-action-btn labo-action-btn--danger" title="Supprimer" onclick="openDelModal(${l.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                    </button>
                </div>
            </td>
        </tr>`;
    })
    .join("");
}

function renderPagination() {
  const pag = document.getElementById("pagination");
  if (!pag) return;

  const totalPages = Math.ceil(filtered.length / perPage);

  if (totalPages <= 1) {
    pag.innerHTML = "";
    return;
  }

  let html = "";

  // Bouton précédent
  html += `<button class="labo-pag-btn" onclick="goPage(${currentPage - 1})" ${currentPage === 1 ? "disabled" : ""}>‹</button>`;

  // Numéros de pages avec ellipses
  for (let i = 1; i <= totalPages; i++) {
    // Afficher : première page, dernière page, et pages proches de la page actuelle
    if (i === 1 || i === totalPages || Math.abs(i - currentPage) <= 2) {
      html += `<button class="labo-pag-btn ${i === currentPage ? "active" : ""}" onclick="goPage(${i})">${i}</button>`;
    }
    // Afficher ellipses si nécessaire
    else if (Math.abs(i - currentPage) === 3) {
      html += `<span class="labo-pag-ellipsis">…</span>`;
    }
  }

  // Bouton suivant
  html += `<button class="labo-pag-btn" onclick="goPage(${currentPage + 1})" ${currentPage === totalPages ? "disabled" : ""}>›</button>`;

  pag.innerHTML = html;
}

function goPage(n) {
  const totalPages = Math.ceil(filtered.length / perPage);
  if (n < 1 || n > totalPages) return;
  currentPage = n;
  renderTable();
  renderPagination();

  // Scroll vers le haut du tableau
  document
    .getElementById("mainTable")
    .scrollIntoView({ behavior: "smooth", block: "start" });
}

function changePerPage(val) {
  perPage = parseInt(val) || 20;
  currentPage = 1;
  renderTable();
  renderPagination();
}

/* ── Modal Ajout / Édition ───────────────────────────────── */
function openAddModal() {
  document.getElementById("editId").value = "";
  document.getElementById("modalTitle").textContent = "Nouveau laboratoire";
  document.getElementById("modalSub").textContent =
    "Ajouter un laboratoire pharmaceutique";
  document.getElementById("inputNom").value = "";
  document.getElementById("inputPays").value = "";
  document.getElementById("inputAgence").value = "";
  document.getElementById("errNom").textContent = "";
  document.getElementById("errAgence").textContent = "";
  document.getElementById("inputNom").classList.remove("error");
  document.getElementById("inputAgence").classList.remove("error");
  document.getElementById("formOverlay").classList.add("open");
}

function openEditModal(id) {
  const l = ALL_DATA.find((x) => x.id === id);
  if (!l) return;
  document.getElementById("editId").value = id;
  document.getElementById("modalTitle").textContent = "Modifier le laboratoire";
  document.getElementById("modalSub").textContent = l.nom_laboratoire;
  document.getElementById("inputNom").value = l.nom_laboratoire;
  document.getElementById("inputPays").value = l.pays || "";
  document.getElementById("inputAgence").value = l.agence_id || "";
  document.getElementById("errNom").textContent = "";
  document.getElementById("errAgence").textContent = "";
  document.getElementById("inputNom").classList.remove("error");
  document.getElementById("inputAgence").classList.remove("error");
  document.getElementById("formOverlay").classList.add("open");
}

function closeFormModal() {
  document.getElementById("formOverlay").classList.remove("open");
}

async function saveLabo() {
  const id = document.getElementById("editId").value;
  const nom = document.getElementById("inputNom").value.trim();
  const pays = document.getElementById("inputPays").value.trim();
  const agenceId = document.getElementById("inputAgence").value;

  let valid = true;
  if (!nom) {
    document.getElementById("errNom").textContent = "Le nom est obligatoire";
    document.getElementById("inputNom").classList.add("error");
    valid = false;
  } else {
    document.getElementById("errNom").textContent = "";
    document.getElementById("inputNom").classList.remove("error");
  }

  if (!agenceId) {
    document.getElementById("errAgence").textContent =
      "L'agence est obligatoire";
    document.getElementById("inputAgence").classList.add("error");
    valid = false;
  } else {
    document.getElementById("errAgence").textContent = "";
    document.getElementById("inputAgence").classList.remove("error");
  }

  if (!valid) return;

  const btn = document.getElementById("btnSave");
  btn.disabled = true;
  btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:labo-spin .7s linear infinite;width:14px;height:14px"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Enregistrement…`;

  const payload = {
    action: id ? "update" : "create",
    nom_laboratoire: nom,
    pays: pays,
    agence_id: parseInt(agenceId),
  };
  if (id) payload.id = parseInt(id);

  try {
    const res = await fetch(API_BASE, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (data.success) {
      if (id) {
        const idx = ALL_DATA.findIndex((x) => x.id === parseInt(id));
        if (idx !== -1) ALL_DATA[idx] = data.data;
      } else {
        ALL_DATA.push(data.data);
        ALL_DATA.sort((a, b) =>
          (a.nom_laboratoire || "").localeCompare(b.nom_laboratoire || ""),
        );
      }
      closeFormModal();
      applyFilters();
      toast(
        id
          ? "Laboratoire modifié avec succès"
          : "Laboratoire ajouté avec succès",
        "success",
      );
    } else {
      throw new Error(data.message || "Erreur serveur");
    }
  } catch (e) {
    toast(e.message || "Erreur réseau", "error");
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg> Enregistrer`;
  }
}

/* ── Modal suppression ───────────────────────────────────── */
function openDelModal(id) {
  const l = ALL_DATA.find((x) => x.id === id);
  if (!l) return;
  pendingDeleteId = id;
  document.getElementById("delMsg").textContent =
    `${l.nom_laboratoire} — ${l.agence_nom || "Sans agence"}`;
  document.getElementById("delOverlay").classList.add("open");
}

function closeDelModal() {
  document.getElementById("delOverlay").classList.remove("open");
  pendingDeleteId = null;
}

document
  .getElementById("btnConfirmDel")
  .addEventListener("click", async function () {
    if (!pendingDeleteId) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;animation:labo-spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Suppression…`;

    try {
      const res = await fetch(API_BASE, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "delete", id: pendingDeleteId }),
      });
      const data = await res.json();

      if (data.success) {
        const idx = ALL_DATA.findIndex((x) => x.id === pendingDeleteId);
        if (idx !== -1) ALL_DATA.splice(idx, 1);
        closeDelModal();
        applyFilters();
        toast("Laboratoire supprimé avec succès", "success");
      } else {
        throw new Error(data.message || "Erreur");
      }
    } catch (e) {
      closeDelModal();
      toast(e.message || "Erreur réseau", "error");
    } finally {
      btn.disabled = false;
      btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg> Supprimer`;
    }
  });

/* ── Toast ───────────────────────────────────────────────── */
function toast(msg, type = "default") {
  const wrap = document.getElementById("toastWrap");
  const el = document.createElement("div");
  const iconOk = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`;
  const iconErr = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`;
  el.className = `labo-toast labo-toast--${type}`;
  el.innerHTML = `${type === "success" ? iconOk : type === "error" ? iconErr : ""}${msg}`;
  wrap.appendChild(el);
  setTimeout(() => {
    el.classList.add("out");
    el.addEventListener("animationend", () => el.remove());
  }, 3500);
}

/* ── Fermeture modals ────────────────────────────────────── */
document.getElementById("formOverlay").addEventListener("click", function (e) {
  if (e.target === this) closeFormModal();
});

document.getElementById("delOverlay").addEventListener("click", function (e) {
  if (e.target === this) closeDelModal();
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeFormModal();
    closeDelModal();
  }
});

/* ── Écouter les filtres ─────────────────────────────────── */
["searchInput"].forEach((id) => {
  const el = document.getElementById(id);
  if (el) el.addEventListener("input", applyFilters);
});

["filterAgence"].forEach((id) => {
  const el = document.getElementById(id);
  if (el) el.addEventListener("change", applyFilters);
});

/* ── Init ────────────────────────────────────────────────── */
applyFilters();
