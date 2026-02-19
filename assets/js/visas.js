/* ═══════════════════════════════════════════════════════════
   visas.js — DPM Archive
   CRUD complet : filtres, drawer, modal add/edit + produits, delete
═══════════════════════════════════════════════════════════ */

/* ── État global ── */
let pendingDelId = null;
let activeDrawerId = null;

/* Produits sélectionnés dans la modal (en mémoire) */
let selectedProduits = []; // [{ produit_id, nom, forme, dosage, quantite, unite }]

/* ════════════════════════════════════════════════════════════
   HELPERS
════════════════════════════════════════════════════════════ */
function fmtDate(s) {
  if (!s || s === "0000-00-00" || s === "0000-00-00 00:00:00") return "—";
  const [y, m, d] = s.slice(0, 10).split("-");
  return `${d}/${m}/${y}`;
}

function dateRenouvellement(dateDecision) {
  if (!dateDecision) return null;
  const y = parseInt(dateDecision.slice(0, 4), 10) + 4;
  return `${y}-02-28`;
}

function updateHintRenouv() {
  const d = document.getElementById("fDateDecision").value;
  const el = document.getElementById("hintRenouv");
  if (!d) {
    el.textContent = "";
    return;
  }
  const yr = parseInt(d.slice(0, 4), 10) + 4;
  el.textContent = `↳ Renouvellement le 28/02/${yr}`;
}

function typeSlugJS(t) {
  const tl = (t || "").toLowerCase().normalize("NFD").replace(/\p{M}/gu, "");
  if (/delegues/.test(tl) && !/campagne/.test(tl)) return "delegues";
  if (/campagne/.test(tl)) return "campagne";
  if (/agences/.test(tl)) return "agences";
  if (/grossistes/.test(tl)) return "grossistes";
  if (/organismes/.test(tl)) return "organismes";
  if (/structures/.test(tl)) return "structures";
  if (/enlevement|enlèvement/.test(tl)) return "enlevement";
  if (/cartes/.test(tl)) return "cartes";
  if (/destruction/.test(tl)) return "destruction";
  if (/laboratoires/.test(tl)) return "laboratoires";
  return "autre";
}

function statutBadge(s) {
  const map = {
    Approuvé: "badge--green",
    Rejeté: "badge--red",
    Suspendu: "badge--amber",
    "En attente": "badge--gray",
  };
  return `<span class="badge ${map[s] || "badge--gray"}">${s}</span>`;
}

/* ════════════════════════════════════════════════════════════
   FILTRES
════════════════════════════════════════════════════════════ */
function applyFilters() {
  const search = document
    .getElementById("searchInput")
    .value.toLowerCase()
    .trim();
  const type = document.getElementById("filterType").value.toLowerCase();
  const statut = document.getElementById("filterStatut").value;

  const rows = document.querySelectorAll(".visa-row");
  let visible = 0;

  rows.forEach((row) => {
    const rowSearch = row.dataset.search || "";
    const rowType = (row.dataset.type || "").toLowerCase();
    const rowStatut = row.dataset.statut || "";

    const okSearch = !search || rowSearch.includes(search);
    const okType = !type || rowType.includes(type);
    const okStatut = !statut || rowStatut === statut;

    if (okSearch && okType && okStatut) {
      row.classList.remove("row-hidden");
      visible++;
    } else {
      row.classList.add("row-hidden");
    }
  });

  document.getElementById("resultCount").textContent =
    `${visible} résultat${visible !== 1 ? "s" : ""}`;

  const empty = document.getElementById("tableEmpty");
  if (empty) empty.style.display = visible === 0 ? "flex" : "none";
}

function resetFilters() {
  document.getElementById("searchInput").value = "";
  document.getElementById("filterType").value = "";
  document.getElementById("filterStatut").value = "";
  applyFilters();
}

/* ════════════════════════════════════════════════════════════
   DRAWER DÉTAIL
════════════════════════════════════════════════════════════ */
function openDrawer(id) {
  const v = ALL_VISAS.find((x) => x.id === id);
  if (!v) return;
  activeDrawerId = id;

  document.getElementById("dTitle").textContent =
    v.numero_dossier || `Visa #${id}`;
  document.getElementById("dSub").textContent = v.type_visa || "";

  /* Produits depuis JSON */
  const produits = v.produits_list || [];

  /* Construction du HTML du body */
  const labNom = v.laboratoire_nom || "—";
  const renouv = v.date_renouvellement ? fmtDate(v.date_renouvellement) : "—";
  const renouvCls = (() => {
    if (!v.date_renouvellement) return "";
    const dr = new Date(v.date_renouvellement);
    const now = new Date();
    const diff = (dr - now) / 86400000;
    if (dr < now) return "date--expired";
    if (diff <= 90) return "date--soon";
    return "";
  })();

  let prodsHtml = "";
  if (produits.length > 0) {
    prodsHtml = produits
      .map((p) => {
        const info = ALL_PRODUITS.find((x) => x.id === p.produit_id);
        const nom = info ? info.nom_produit : `Produit #${p.produit_id}`;
        const meta = info
          ? [info.dosage, info.forme].filter(Boolean).join(" · ")
          : "";
        const qte = p.quantite || 0;
        const unite = p.unite || "";
        return `
            <div class="d-prod-item">
                <div class="d-prod-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>
                <div class="d-prod-name">${nom}${meta ? '<br><small style="color:var(--ink-soft);font-size:.72rem">' + meta + "</small>" : ""}</div>
                <div class="d-prod-qty">${qte > 0 ? qte + " " + unite : unite || "—"}</div>
            </div>`;
      })
      .join("");
  } else {
    prodsHtml =
      '<div class="d-prod-empty">Aucun produit associé à ce visa</div>';
  }

  document.getElementById("drawerBody").innerHTML = `
        <div class="d-section">
            <div class="d-sec-title">Type &amp; Statut</div>
            <div class="d-row"><div class="d-lbl">Type</div><div class="d-val"><span class="type-pill type-pill--${typeSlugJS(v.type_visa)}">${v.type_visa || "—"}</span></div></div>
            <div class="d-row"><div class="d-lbl">Statut</div><div class="d-val">${statutBadge(v.statut)}</div></div>
            <div class="d-row"><div class="d-lbl">Laboratoire</div><div class="d-val">${labNom}</div></div>
            <div class="d-row"><div class="d-lbl">Carte prof.</div><div class="d-val">${v.carte_professionnelle ? '<span style="color:var(--green);font-weight:700">✓ Délivrée</span>' : '<span class="muted">Non</span>'}</div></div>
        </div>
        <div class="d-section">
            <div class="d-sec-title">Dates</div>
            <div class="d-row"><div class="d-lbl">Décision</div><div class="d-val">${fmtDate(v.date_decision)}</div></div>
            <div class="d-row"><div class="d-lbl">Renouvellement</div><div class="d-val ${renouvCls}" style="font-weight:600">${renouv}</div></div>
            <div class="d-row"><div class="d-lbl">Créé le</div><div class="d-val">${fmtDate(v.created_at)}</div></div>
        </div>
        <div class="d-section">
            <div class="d-sec-title">Archivage</div>
            <div class="d-row"><div class="d-lbl">Zone</div><div class="d-val">${v.zone_archive || '<span class="muted">—</span>'}</div></div>
            <div class="d-row"><div class="d-lbl">Box</div><div class="d-val">${v.box_rangement || '<span class="muted">—</span>'}</div></div>
        </div>
        ${v.observations ? `<div class="d-section"><div class="d-sec-title">Observations</div><div style="font-size:.82rem;color:var(--ink-mid);line-height:1.55;padding:10px 12px;background:#F8FAFC;border-radius:8px;border:1px solid var(--border)">${v.observations}</div></div>` : ""}
        <div class="d-section">
            <div class="d-sec-title">Produits (${produits.length})</div>
            ${prodsHtml}
        </div>
    `;

  document.getElementById("dBtnEdit").onclick = () => {
    closeDrawer();
    setTimeout(() => openEditModal(id), 260);
  };

  document.getElementById("drawerBackdrop").classList.add("open");
}

function closeDrawer() {
  document.getElementById("drawerBackdrop").classList.remove("open");
  activeDrawerId = null;
}

/* ════════════════════════════════════════════════════════════
   GESTION PRODUITS DANS LA MODAL
════════════════════════════════════════════════════════════ */
function refreshProdList() {
  const list = document.getElementById("prodList");
  const empty = document.getElementById("prodEmpty");
  const badge = document.getElementById("prodCountBadge");

  /* Vider les items existants (garder le prodEmpty) */
  Array.from(list.children).forEach((c) => {
    if (!c.id || c.id !== "prodEmpty") c.remove();
  });

  if (selectedProduits.length === 0) {
    empty.style.display = "flex";
    list.classList.remove("has-items");
    badge.style.display = "none";
  } else {
    empty.style.display = "none";
    list.classList.add("has-items");
    badge.style.display = "inline-flex";
    badge.textContent = selectedProduits.length;

    selectedProduits.forEach((item, idx) => {
      const div = document.createElement("div");
      div.className = "prod-item";
      div.innerHTML = `
                <div class="prod-item__ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                </div>
                <div class="prod-item__info">
                    <div class="prod-item__name">${item.nom}</div>
                    <div class="prod-item__meta">${[item.dosage, item.forme].filter(Boolean).join(" · ") || "Produit pharmaceutique"}</div>
                </div>
                <div class="prod-item__qty">
                    <input type="number" value="${item.quantite || ""}" min="0" placeholder="Qté"
                           onchange="selectedProduits[${idx}].quantite = parseInt(this.value)||0"
                           title="Quantité">
                    <input type="text" class="prod-item__unite" value="${item.unite || "boîtes"}" placeholder="Unité"
                           onchange="selectedProduits[${idx}].unite = this.value.trim()"
                           title="Unité">
                </div>
                <button class="prod-item__del" onclick="removeProduit(${idx})" title="Retirer ce produit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            `;
      list.appendChild(div);
    });
  }
}

function addProduit() {
  const sel = document.getElementById("prodSelector");
  const qteEl = document.getElementById("prodQte");
  const unEl = document.getElementById("prodUnite");

  const prodId = parseInt(sel.value, 10);
  if (!prodId) {
    toast("Sélectionnez un produit d'abord", "error");
    return;
  }

  /* Évite les doublons */
  if (selectedProduits.find((p) => p.produit_id === prodId)) {
    toast("Ce produit est déjà dans la liste", "error");
    return;
  }

  const opt = sel.options[sel.selectedIndex];
  selectedProduits.push({
    produit_id: prodId,
    nom: opt.dataset.nom || `Produit #${prodId}`,
    forme: opt.dataset.forme || "",
    dosage: opt.dataset.dosage || "",
    quantite: parseInt(qteEl.value, 10) || 0,
    unite: unEl.value.trim() || "boîtes",
  });

  /* Reset */
  sel.value = "";
  qteEl.value = "";
  unEl.value = "boîtes";

  refreshProdList();
}

function removeProduit(idx) {
  selectedProduits.splice(idx, 1);
  refreshProdList();
}

/* ════════════════════════════════════════════════════════════
   MODAL ADD / EDIT
════════════════════════════════════════════════════════════ */
function resetForm() {
  document.getElementById("fId").value = "";
  document.getElementById("fTypeVisa").value = "";
  document.getElementById("fNumeroDossier").value = "";
  document.getElementById("fStatut").value = "En attente";
  document.getElementById("fLaboratoire").value = "";
  document.getElementById("fDateDecision").value = "";
  document.getElementById("fZoneArchive").value = "";
  document.getElementById("fBoxRangement").value = "";
  document.getElementById("fCarte").checked = false;
  document.getElementById("fObservations").value = "";
  document.getElementById("hintRenouv").textContent = "";
  document.getElementById("eTypeVisa").textContent = "";
  document.getElementById("fTypeVisa").classList.remove("error");
  selectedProduits = [];
  refreshProdList();
}

function openAddModal() {
  resetForm();
  document.getElementById("mfTitle").textContent = "Nouveau visa";
  document.getElementById("mfSub").textContent =
    "Remplissez les informations, puis ajoutez les produits concernés";
  document.getElementById("formOverlay").classList.add("open");
}

function openEditModal(id) {
  const v = ALL_VISAS.find((x) => x.id === id);
  if (!v) return;

  resetForm();
  document.getElementById("mfTitle").textContent = "Modifier le visa";
  document.getElementById("mfSub").textContent =
    v.numero_dossier || `Dossier #${id}`;

  document.getElementById("fId").value = id;
  document.getElementById("fTypeVisa").value = v.type_visa || "";
  document.getElementById("fNumeroDossier").value = v.numero_dossier || "";
  document.getElementById("fStatut").value = v.statut || "En attente";
  document.getElementById("fLaboratoire").value = v.laboratoire_id || "";
  document.getElementById("fDateDecision").value = v.date_decision || "";
  document.getElementById("fZoneArchive").value = v.zone_archive || "";
  document.getElementById("fBoxRangement").value = v.box_rangement || "";
  document.getElementById("fCarte").checked = !!v.carte_professionnelle;
  document.getElementById("fObservations").value = v.observations || "";
  updateHintRenouv();

  /* Recharger les produits depuis la liste JSON */
  selectedProduits = [];
  const prodList = v.produits_list || [];
  prodList.forEach((p) => {
    const info = ALL_PRODUITS.find((x) => x.id === p.produit_id);
    selectedProduits.push({
      produit_id: p.produit_id,
      nom: info ? info.nom_produit : `Produit #${p.produit_id}`,
      forme: info ? info.forme || "" : "",
      dosage: info ? info.dosage || "" : "",
      quantite: p.quantite || 0,
      unite: p.unite || "boîtes",
    });
  });
  refreshProdList();

  document.getElementById("formOverlay").classList.add("open");
}

function closeFormModal() {
  document.getElementById("formOverlay").classList.remove("open");
}

/* ════════════════════════════════════════════════════════════
   SAVE (CREATE / UPDATE)
════════════════════════════════════════════════════════════ */
async function saveVisa() {
  /* Validation */
  const typeEl = document.getElementById("fTypeVisa");
  const eType = document.getElementById("eTypeVisa");
  if (!typeEl.value) {
    eType.textContent = "Champ obligatoire";
    typeEl.classList.add("error");
    return;
  }
  eType.textContent = "";
  typeEl.classList.remove("error");

  const id = document.getElementById("fId").value;
  const btn = document.getElementById("btnSave");
  btn.disabled = true;
  btn.innerHTML = `<svg style="animation:spin .7s linear infinite;width:14px;height:14px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Enregistrement…`;

  /* Construire les produits JSON (sans les infos de rendu) */
  const produits = selectedProduits.map((p) => ({
    produit_id: p.produit_id,
    quantite: p.quantite || 0,
    unite: p.unite || "",
  }));

  /* Lire les valeurs à jour depuis le DOM pour quantités/unités */
  const items = document.querySelectorAll(".prod-item");
  items.forEach((item, idx) => {
    const qInput = item.querySelector('input[type="number"]');
    const uInput = item.querySelector(".prod-item__unite");
    if (produits[idx]) {
      produits[idx].quantite = parseInt(qInput?.value || "0", 10) || 0;
      produits[idx].unite = uInput?.value?.trim() || "boîtes";
    }
  });

  const payload = {
    action: id ? "update" : "create",
    type_visa: document.getElementById("fTypeVisa").value,
    numero_dossier:
      document.getElementById("fNumeroDossier").value.trim() || null,
    statut: document.getElementById("fStatut").value,
    laboratoire_id: document.getElementById("fLaboratoire").value || null,
    date_decision: document.getElementById("fDateDecision").value || null,
    zone_archive: document.getElementById("fZoneArchive").value || null,
    box_rangement:
      document.getElementById("fBoxRangement").value.trim() || null,
    carte_professionnelle: document.getElementById("fCarte").checked ? 1 : 0,
    observations: document.getElementById("fObservations").value.trim() || null,
    produits: produits.length > 0 ? produits : null,
  };
  if (id) payload.id = parseInt(id, 10);

  try {
    const res = await fetch(API_URL, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (data.success) {
      const d = data.data;
      d.produits_list = d.produits
        ? typeof d.produits === "string"
          ? JSON.parse(d.produits)
          : d.produits
        : [];

      if (id) {
        const i = ALL_VISAS.findIndex((x) => x.id === parseInt(id, 10));
        if (i !== -1) ALL_VISAS[i] = d;
        else ALL_VISAS.unshift(d);
      } else {
        ALL_VISAS.unshift(d);
      }

      closeFormModal();
      rebuildTable();
      toast(
        id ? "Visa modifié avec succès" : "Visa créé avec succès",
        "success",
      );
    } else {
      throw new Error(data.message || "Erreur serveur");
    }
  } catch (e) {
    toast(e.message || "Erreur réseau", "error");
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Enregistrer`;
  }
}

/* ════════════════════════════════════════════════════════════
   REBUILD TABLE (après save)
════════════════════════════════════════════════════════════ */
function rebuildTable() {
  const tbody = document.getElementById("visaBody");
  tbody.innerHTML = ALL_VISAS.map((v) => {
    const nb = (v.produits_list || []).length;
    const renouv = v.date_renouvellement;
    let rc = "";
    if (renouv) {
      const dr = new Date(renouv);
      const now = new Date();
      const diff = (dr - now) / 86400000;
      rc = dr < now ? "date--expired" : diff <= 90 ? "date--soon" : "";
    }
    const searchStr = [
      v.numero_dossier,
      v.type_visa,
      v.laboratoire_nom,
      v.observations,
    ]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();

    return `<tr class="visa-row"
            data-id="${v.id}"
            data-type="${escHtml(v.type_visa || "")}"
            data-statut="${escHtml(v.statut || "")}"
            data-search="${escHtml(searchStr)}">
            <td class="td-mono">${escHtml(v.numero_dossier || "—")}</td>
            <td><span class="type-pill type-pill--${typeSlugJS(v.type_visa)}">${escHtml(v.type_visa || "")}</span></td>
            <td class="td-lab">${v.laboratoire_nom ? escHtml(v.laboratoire_nom) : '<span class="muted">—</span>'}</td>
            <td class="td-center">
                ${
                  nb > 0
                    ? `<button class="prod-badge-btn" onclick="openDrawer(${v.id})">${nb} produit${nb > 1 ? "s" : ""}</button>`
                    : '<span class="muted">—</span>'
                }
            </td>
            <td class="td-date">${fmtDate(v.date_decision)}</td>
            <td class="td-date ${rc}">${renouv ? fmtDate(renouv) : '<span class="muted">—</span>'}</td>
            <td>${statutBadge(v.statut)}</td>
            <td class="td-center">
                ${
                  v.carte_professionnelle
                    ? '<span class="carte-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span>'
                    : '<span class="muted">—</span>'
                }
            </td>
            <td class="td-archive">
                ${
                  v.zone_archive
                    ? `<span class="archive-pill">${escHtml(v.zone_archive)}</span>${v.box_rangement ? '<br><small class="box-ref">' + escHtml(v.box_rangement) + "</small>" : ""}`
                    : '<span class="muted">—</span>'
                }
            </td>
            <td class="td-actions">
                <button class="action-btn" title="Détail" onclick="openDrawer(${v.id})">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <button class="action-btn" title="Modifier" onclick="openEditModal(${v.id})">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="action-btn action-btn--danger" title="Supprimer" onclick="openDelModal(${v.id}, '${escAttr(v.numero_dossier || "ce visa")}')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                </button>
            </td>
        </tr>`;
  }).join("");

  applyFilters();
}

function escHtml(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}
function escAttr(s) {
  return String(s).replace(/'/g, "\\'").replace(/"/g, '\\"');
}

/* ════════════════════════════════════════════════════════════
   MODAL SUPPRESSION
════════════════════════════════════════════════════════════ */
function openDelModal(id, label) {
  pendingDelId = id;
  document.getElementById("delMsg").textContent = `« ${label} »`;
  document.getElementById("delOverlay").classList.add("open");
}
function closeDelModal() {
  document.getElementById("delOverlay").classList.remove("open");
  pendingDelId = null;
}

document
  .getElementById("btnConfirmDel")
  .addEventListener("click", async function () {
    if (!pendingDelId) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = `<svg style="width:14px;height:14px;animation:spin .7s linear infinite" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Suppression…`;

    try {
      const res = await fetch(API_URL, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "delete", id: pendingDelId }),
      });
      const data = await res.json();

      if (data.success) {
        const i = ALL_VISAS.findIndex((x) => x.id === pendingDelId);
        if (i !== -1) ALL_VISAS.splice(i, 1);
        closeDelModal();
        rebuildTable();
        toast("Visa supprimé avec succès", "success");
      } else {
        throw new Error(data.message || "Erreur");
      }
    } catch (e) {
      closeDelModal();
      toast(e.message || "Erreur réseau", "error");
    } finally {
      btn.disabled = false;
      btn.innerHTML = `<svg style="width:13px;height:13px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg> Supprimer`;
    }
  });

/* ════════════════════════════════════════════════════════════
   TOASTS
════════════════════════════════════════════════════════════ */
function toast(msg, type = "default") {
  const w = document.getElementById("toastWrap");
  const el = document.createElement("div");
  const ok = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`;
  const err = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`;
  el.className = `toast toast--${type}`;
  el.innerHTML = `${type === "success" ? ok : type === "error" ? err : ""}${msg}`;
  w.appendChild(el);
  setTimeout(() => {
    el.classList.add("out");
    el.addEventListener("animationend", () => el.remove());
  }, 3500);
}

/* ════════════════════════════════════════════════════════════
   FERMETURE (backdrop / Escape)
════════════════════════════════════════════════════════════ */
document.getElementById("formOverlay").addEventListener("click", (e) => {
  if (e.target === document.getElementById("formOverlay")) closeFormModal();
});
document.getElementById("delOverlay").addEventListener("click", (e) => {
  if (e.target === document.getElementById("delOverlay")) closeDelModal();
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeFormModal();
    closeDelModal();
    closeDrawer();
  }
});

/* ════════════════════════════════════════════════════════════
   LISTENERS
════════════════════════════════════════════════════════════ */
document.getElementById("searchInput").addEventListener("input", applyFilters);
document.getElementById("filterType").addEventListener("change", applyFilters);
document
  .getElementById("filterStatut")
  .addEventListener("change", applyFilters);

/* ── Init ── */
applyFilters();
