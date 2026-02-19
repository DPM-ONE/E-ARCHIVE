/* ═══════════════════════════════════════════════════════════
   professionnels.js — DPM Archive
   Catégorie par défaut : "delegue" (délégués médicaux)

   Fonctions présentes dans la DB :
     delegue     → Délégué Médical / Déléguée Médicale / Délégué Médical Senior
     pharmacien  → Pharmacien Titulaire / Pharmacienne Adjointe / etc.
     depositaire → Pharmacien Dépositaire / Pharmacienne Dépositaire /
                   Responsable Dépôt Pharmaceutique
═══════════════════════════════════════════════════════════ */

/* ── État global ─────────────────────────────────────────── */
let filtered       = [];
let currentPage    = 1;
let perPage        = 12;
let sortKey        = 'nom';
let sortDir        = 1;
let currentView    = 'cards';
let currentCat     = 'delegue';   // ← DÉFAUT : délégués médicaux
let activeDrawerId = null;
let pendingDelId   = null;

/* ── Détection catégorie (robuste, insensible aux accents) ── */
function detectCat(f) {
    // Même logique que PHP : NFD normalize + suppression des combining marks (accents)
    const fn = (f || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{M}/gu, ''); // supprime tous les diacritiques (Unicode property escape)

    // 1. Délégué  → 'delegue' après normalisation
    if (/delegu/.test(fn)) return 'delegue';

    // 2. Dépôtaire AVANT pharmacien (car "Pharmacien Dépositaire" contient les deux)
    if (/depositaire/.test(fn)
     || /responsable.{0,6}dep/.test(fn)
     || /depot.{0,8}pharm/.test(fn)) return 'depositaire';

    // 3. Pharmacien pur
    if (/pharmacien/.test(fn)) return 'pharmacien';
    return 'autre';
}

/* ── Helpers date ── */
const MOIS = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
function fmtDate(s)     { if (!s || s === '0000-00-00') return '—'; const [y,m,d] = s.split('-'); return `${d}/${m}/${y}`; }
function fmtDateLong(s) { if (!s || s === '0000-00-00') return '—'; const [y,m,d] = s.split('-').map(Number); return `${d} ${MOIS[m-1]} ${y}`; }
function calcAge(dn) {
    if (!dn) return null;
    const [y,m,d] = dn.split('-').map(Number), t = new Date();
    let a = t.getFullYear() - y;
    if (t.getMonth()+1 < m || (t.getMonth()+1 === m && t.getDate() < d)) a--;
    return a;
}
function isExpired(dv)   { return dv && dv !== '0000-00-00' && new Date(dv) < new Date(); }
function expiresSoon(dv) {
    if (!dv || dv === '0000-00-00') return false;
    const d = new Date(dv), n = new Date();
    return d >= n && d < new Date(n.getTime() + 90*24*60*60*1000);
}

/* ── SVG genre ── */
const SVG_M = `<img src="../assets/img/face-man.png" alt="Homme" class="avatar-img">`;
const SVG_F = `<img src="../assets/img/face-woman.png" alt="Femme" class="avatar-img">`;

/* ── Labels catégorie ── */
function catLabel(c) { return {delegue:'Délégué médical',pharmacien:'Pharmacien',depositaire:'Dépôtaire',autre:'Autre'}[c] || c; }
function catShort(c) { return {delegue:'Délégué',pharmacien:'Pharmacien',depositaire:'Dépôtaire',autre:'Autre'}[c] || c; }

/* ── Badge validité ── */
function validBadge(dv) {
    if (isExpired(dv))   return `<span class="status-badge status-badge--expired">Expiré</span>`;
    if (expiresSoon(dv)) return `<span class="status-badge status-badge--soon">Expire bientôt</span>`;
    return `<span class="status-badge status-badge--actif">Valide</span>`;
}

/* ── SVG icônes rattachement ── */
const ICO_AGENCE = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>`;
const ICO_PHARMA = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>`;
const ICO_DEPOT  = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>`;
const ICO_PHONE  = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.68 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6.29 6.29l.78-.78a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>`;
const ICO_CLOCK  = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`;

/* ── Construire les lignes de rattachement ─────────────────
 * Utilise les champs injectés par PHP depuis les JOIN :
 *   agence_nom       ← agences_dpm.nom_agence
 *   pharmacie_nom    ← pharmacies_dpm.nom_pharmacie
 *   depot_nom        ← depots_dpm.depot_pharmaceutique
 */
function buildRattach(p) {
    const rows = [];
    if (p.agence_nom)    rows.push({ ico: ICO_AGENCE, label: 'Agence',    val: p.agence_nom });
    if (p.pharmacie_nom) rows.push({ ico: ICO_PHARMA, label: 'Pharmacie', val: p.pharmacie_nom });
    if (p.depot_nom)     rows.push({ ico: ICO_DEPOT,  label: 'Dépôt',     val: p.depot_nom });
    return rows;
}

/* Helper : injecter un svg inline avec taille forcée */
function inlineIco(svgStr, size = 13) {
    return svgStr.replace('<svg ', `<svg style="width:${size}px;height:${size}px;flex-shrink:0" `);
}

/* ════════════════════════════════════════════════════════════
   FILTRES
════════════════════════════════════════════════════════════ */
function applyFilters() {
    const search = document.getElementById('searchInput').value.toLowerCase().trim();
    const sexe   = document.getElementById('filterSexe').value;
    const statut = document.getElementById('filterStatut').value;

    filtered = ALL_DATA.filter(p => {
        // 1. Catégorie
        if (currentCat && p.categorie !== currentCat) return false;
        // 2. Sexe
        if (sexe && p.sexe !== sexe) return false;
        // 3. Statut
        if (statut === 'actif'   && !p.is_active)                 return false;
        if (statut === 'inactif' &&  p.is_active)                 return false;
        if (statut === 'expire'  && !isExpired(p.date_validite))  return false;
        if (statut === 'bientot' && !expiresSoon(p.date_validite)) return false;
        // 4. Recherche texte
        if (search) {
            const hay = [
                p.nom, p.prenom, p.fonction, p.numero_cni,
                p.telephone, p.email, p.lieu_naissance,
                p.agence_nom, p.pharmacie_nom, p.depot_nom
            ].filter(Boolean).join(' ').toLowerCase();
            if (!hay.includes(search)) return false;
        }
        return true;
    });

    /* Tri */
    filtered.sort((a, b) => {
        let av = a[sortKey] ?? '', bv = b[sortKey] ?? '';
        if (typeof av === 'boolean') { av = av ? 1 : 0; bv = bv ? 1 : 0; }
        if (typeof av === 'string')  { av = av.toLowerCase(); bv = bv.toLowerCase(); }
        return av < bv ? -sortDir : av > bv ? sortDir : 0;
    });

    currentPage = 1;
    document.getElementById('resultsCount').innerHTML =
        `<strong>${filtered.length}</strong> résultat${filtered.length !== 1 ? 's' : ''}`;

    if (currentView === 'cards') { renderCards(); renderPag('paginationCards'); }
    else                         { renderTable(); renderPag('paginationTable'); }
}

function setCategory(btn, cat) {
    currentCat = cat;
    document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('cat-tab--active'));
    btn.classList.add('cat-tab--active');
    applyFilters();
}

function resetFilters() {
    document.getElementById('searchInput').value  = '';
    document.getElementById('filterSexe').value   = '';
    document.getElementById('filterStatut').value = '';
    currentCat = 'delegue';
    document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('cat-tab--active'));
    document.querySelector('.cat-tab[data-cat="delegue"]').classList.add('cat-tab--active');
    applyFilters();
}

function setView(v) {
    currentView = v;
    perPage = v === 'table' ? 20 : 12;
    document.getElementById('viewCards').style.display = v === 'cards' ? '' : 'none';
    document.getElementById('viewTable').style.display = v === 'table' ? '' : 'none';
    document.getElementById('btnCards').classList.toggle('view-btn--active', v === 'cards');
    document.getElementById('btnTable').classList.toggle('view-btn--active', v === 'table');
    applyFilters();
}

/* ════════════════════════════════════════════════════════════
   VUE CARDS
════════════════════════════════════════════════════════════ */
function renderCards() {
    const grid  = document.getElementById('cardsGrid');
    const empty = document.getElementById('emptyCards');
    const page  = filtered.slice((currentPage - 1) * perPage, currentPage * perPage);

    if (!filtered.length) { grid.innerHTML = ''; empty.style.display = ''; return; }
    empty.style.display = 'none';

    grid.innerHTML = page.map(p => {
        const cat     = p.categorie || 'autre';
        const male    = p.sexe === 'Masculin';
        const age     = calcAge(p.date_naissance);
        const rattach = buildRattach(p);
        const expired = isExpired(p.date_validite);
        const soon    = expiresSoon(p.date_validite);
        const valCls  = expired ? 'pro-card__validity-date--expired'
                      : soon    ? 'pro-card__validity-date--soon'
                                : 'pro-card__validity-date--ok';

        // Rattachement : max 2 lignes sur la card
        const rattachHTML = rattach.slice(0, 2).map(r =>
            `<div class="pro-card__row pro-card__row--bold">
                ${inlineIco(r.ico, 12)}
                <span>${r.val}</span>
            </div>`
        ).join('');

        return `
        <div class="pro-card${!p.is_active ? ' pro-card--inactive' : ''}" onclick="openDrawer(${p.id})">
            <div class="pro-card__cat-bar pro-card__cat-bar--${cat}"></div>
            <div class="pro-card__header">
                <span class="pro-card__cat-badge pro-card__cat-badge--${cat}">${catShort(cat)}</span>
                <div class="pro-card__avatar pro-card__avatar--${male ? 'male' : 'female'}">${male ? SVG_M : SVG_F}</div>
                <div class="pro-card__prenom">${p.prenom || ''}</div>
                <div class="pro-card__name">${p.nom || ''}</div>
                <div class="pro-card__fonction">${p.fonction || ''}</div>
            </div>
            <div class="pro-card__body">
                ${rattachHTML}
                ${p.telephone
                    ? `<div class="pro-card__row">${inlineIco(ICO_PHONE,12)}<span>${p.telephone}</span></div>`
                    : ''}
                ${age
                    ? `<div class="pro-card__row">${inlineIco(ICO_CLOCK,12)}<span>${age} ans · ${p.lieu_naissance || ''}</span></div>`
                    : ''}
            </div>
            <div class="pro-card__footer">
                <div class="pro-card__validity">
                    <div class="pro-card__validity-label">Validité</div>
                    <div class="pro-card__validity-date ${valCls}">${fmtDate(p.date_validite)}</div>
                </div>
                <span class="${p.is_active ? 'status-badge status-badge--actif' : 'status-badge status-badge--inactif'}">
                    ${p.is_active ? 'Actif' : 'Inactif'}
                </span>
                <div class="pro-card__actions" onclick="event.stopPropagation()">
                    <button class="card-btn" title="Modifier" onclick="openEditModal(${p.id})">${inlineIco('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',12)}</button>
                    <button class="card-btn card-btn--danger" title="Supprimer" onclick="openDelModal(${p.id})">${inlineIco('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>',12)}</button>
                </div>
            </div>
        </div>`;
    }).join('');
}

/* ════════════════════════════════════════════════════════════
   VUE TABLEAU
════════════════════════════════════════════════════════════ */
function sortBy(k) {
    if (sortKey === k) sortDir *= -1; else { sortKey = k; sortDir = 1; }
    document.querySelectorAll('thead th').forEach(t => t.classList.remove('sort-asc','sort-desc'));
    const map = { nom:'th_nom', fonction:'th_fonc', date_validite:'th_val', is_active:'th_stat' };
    if (map[k]) document.getElementById(map[k])?.classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');
    applyFilters();
}

function renderTable() {
    const tbody = document.getElementById('tableBody');
    const empty = document.getElementById('emptyTable');
    const page  = filtered.slice((currentPage - 1) * perPage, currentPage * perPage);

    if (!filtered.length) { tbody.innerHTML = ''; empty.style.display = ''; return; }
    empty.style.display = 'none';

    tbody.innerHTML = page.map(p => {
        const male    = p.sexe === 'Masculin';
        const cat     = p.categorie || 'autre';
        const age     = calcAge(p.date_naissance);
        const rattach = buildRattach(p);

        const rattachHTML = rattach.length
            ? rattach.map(r =>
                `<div style="display:flex;align-items:center;gap:5px;line-height:1.8">
                    ${inlineIco(r.ico, 11)}
                    <span style="font-size:.78rem;color:var(--ink-mid)">${r.val}</span>
                </div>`).join('')
            : `<span style="color:var(--ink-soft);font-style:italic;font-size:.78rem">—</span>`;

        return `<tr onclick="openDrawer(${p.id})">
            <td>
                <div style="display:flex;align-items:center;gap:9px">
                    <span class="gender-icon gender-icon--${male ? 'male':'female'}">${male ? SVG_M : SVG_F}</span>
                    <div>
                        <div style="font-weight:600;font-size:.85rem">${p.prenom} ${p.nom}</div>
                        <div style="font-size:.75rem;color:var(--ink-soft)">${p.lieu_naissance || ''}${age ? ` · ${age} ans` : ''}</div>
                    </div>
                </div>
            </td>
            <td>
                <div style="font-size:.82rem;font-weight:500">${p.fonction || '—'}</div>
                <span class="cat-micro cat-micro--${cat}">${catShort(cat)}</span>
            </td>
            <td>${rattachHTML}</td>
            <td>
                <div style="font-size:.82rem">${p.telephone || '—'}</div>
                ${p.email ? `<div style="font-size:.75rem;color:var(--ink-soft)">${p.email}</div>` : ''}
            </td>
            <td>
                ${validBadge(p.date_validite)}
                <div style="font-size:.74rem;color:var(--ink-soft);margin-top:2px">${fmtDate(p.date_validite)}</div>
            </td>
            <td>
                <span class="${p.is_active ? 'status-badge status-badge--actif':'status-badge status-badge--inactif'}">
                    ${p.is_active ? 'Actif':'Inactif'}
                </span>
            </td>
            <td>
                <div class="td-actions" onclick="event.stopPropagation()">
                    <button class="action-btn" title="Modifier" onclick="openEditModal(${p.id})">
                        ${inlineIco('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',12)}
                    </button>
                    <button class="action-btn action-btn--danger" title="Supprimer" onclick="openDelModal(${p.id})">
                        ${inlineIco('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>',12)}
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

/* ── Pagination ── */
function renderPag(cid) {
    const el = document.getElementById(cid); if (!el) return;
    const tot = Math.ceil(filtered.length / perPage);
    if (tot <= 1) { el.innerHTML = ''; return; }
    let h = `<button class="pag-btn" onclick="goPage(${currentPage-1},'${cid}')" ${currentPage===1?'disabled':''}>‹</button>`;
    for (let i = 1; i <= tot; i++) {
        if (i===1 || i===tot || Math.abs(i-currentPage)<=2)
            h += `<button class="pag-btn ${i===currentPage?'active':''}" onclick="goPage(${i},'${cid}')">${i}</button>`;
        else if (Math.abs(i-currentPage)===3)
            h += `<span class="pag-ellipsis">…</span>`;
    }
    h += `<button class="pag-btn" onclick="goPage(${currentPage+1},'${cid}')" ${currentPage===tot?'disabled':''}>›</button>`;
    el.innerHTML = h;
}
function goPage(n, cid) {
    const tot = Math.ceil(filtered.length / perPage);
    if (n < 1 || n > tot) return;
    currentPage = n;
    if (currentView === 'cards') { renderCards(); renderPag('paginationCards'); }
    else { renderTable(); renderPag('paginationTable'); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ════════════════════════════════════════════════════════════
   DRAWER — Fiche détaillée
════════════════════════════════════════════════════════════ */
function openDrawer(id) {
    const p = ALL_DATA.find(x => x.id === id); if (!p) return;
    activeDrawerId = id;
    const male    = p.sexe === 'Masculin';
    const age     = calcAge(p.date_naissance);
    const rattach = buildRattach(p);
    const cat     = p.categorie || 'autre';

    const av = document.getElementById('dAvatar');
    av.className = `drawer-avatar drawer-avatar--${male ? 'male':'female'}`;
    av.innerHTML = male ? SVG_M : SVG_F;

    document.getElementById('dName').textContent     = `${p.prenom} ${p.nom}`;
    document.getElementById('dFonction').textContent = p.fonction || '';
    document.getElementById('btnDrawerEdit').onclick = () => { closeDrawer(); setTimeout(() => openEditModal(id), 260); };

    document.getElementById('drawerBody').innerHTML = `
        <div class="d-section">
            <div class="d-section-title">Identité</div>
            <div class="d-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;flex-shrink:0"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                <div><div class="d-row-label">Genre</div><div class="d-row-val">${p.sexe}</div></div>
            </div>
            <div class="d-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;flex-shrink:0"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <div><div class="d-row-label">Date de naissance</div><div class="d-row-val">${fmtDateLong(p.date_naissance)}${age ? ` (${age} ans)` : ''}</div></div>
            </div>
            <div class="d-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;flex-shrink:0"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <div><div class="d-row-label">Lieu de naissance</div><div class="d-row-val">${p.lieu_naissance || '—'}</div></div>
            </div>
            ${p.numero_cni ? `
            <div class="d-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;flex-shrink:0"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                <div><div class="d-row-label">CNI</div><div class="d-row-val">${p.numero_cni}</div></div>
            </div>` : ''}
        </div>

        <div class="d-section">
            <div class="d-section-title">Rattachement</div>
            ${rattach.length
                ? rattach.map(r => `
                    <div class="d-row">
                        ${inlineIco(r.ico, 14)}
                        <div><div class="d-row-label">${r.label}</div><div class="d-row-val">${r.val}</div></div>
                    </div>`).join('')
                : `<div class="d-row"><div class="d-row-val muted">Aucun rattachement enregistré</div></div>`
            }
        </div>

        <div class="d-section">
            <div class="d-section-title">Contact</div>
            ${p.telephone ? `
            <div class="d-row">
                ${inlineIco(ICO_PHONE, 14)}
                <div><div class="d-row-label">Téléphone</div><div class="d-row-val">${p.telephone}</div></div>
            </div>` : ''}
            ${p.email ? `
            <div class="d-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;flex-shrink:0"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <div><div class="d-row-label">Email</div><div class="d-row-val">${p.email}</div></div>
            </div>` : ''}
            ${!p.telephone && !p.email
                ? `<div class="d-row"><div class="d-row-val muted">Aucun contact enregistré</div></div>`
                : ''}
        </div>

        <div class="d-section">
            <div class="d-section-title">Documents &amp; Validité</div>
            <div class="d-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;flex-shrink:0"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <div><div class="d-row-label">Délivré le</div><div class="d-row-val">${fmtDateLong(p.date_delivrance)} · ${p.lieu_delivrance || '—'}</div></div>
            </div>
            <div class="d-row">
                ${inlineIco(ICO_CLOCK, 14)}
                <div><div class="d-row-label">Valide jusqu'au</div><div class="d-row-val">${fmtDateLong(p.date_validite)} ${validBadge(p.date_validite)}</div></div>
            </div>
            <div class="d-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;flex-shrink:0"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <div><div class="d-row-label">Statut</div>
                    <div class="d-row-val">
                        <span class="${p.is_active ? 'status-badge status-badge--actif':'status-badge status-badge--inactif'}">
                            ${p.is_active ? 'Actif':'Inactif'}
                        </span>
                    </div>
                </div>
            </div>
        </div>`;

    document.getElementById('drawerBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    document.getElementById('drawerBackdrop').classList.remove('open');
    document.body.style.overflow = '';
    activeDrawerId = null;
}

document.getElementById('drawerBackdrop').addEventListener('click', e => {
    if (e.target === document.getElementById('drawerBackdrop')) closeDrawer();
});
document.getElementById('btnDrawerDelete').addEventListener('click', () => {
    const id = activeDrawerId; if (!id) return;
    closeDrawer(); setTimeout(() => openDelModal(id), 260);
});

/* ════════════════════════════════════════════════════════════
   MODAL FORMULAIRE
════════════════════════════════════════════════════════════ */
const REQUIRED_FIELDS = [
    ['fPrenom','ePrenom'], ['fNom','eNom'], ['fSexe','eSexe'],
    ['fDateNaiss','eDateNaiss'], ['fFonction','eFonction'],
    ['fDateDeliv','eDateDeliv'], ['fLieuDeliv','eLieuDeliv'], ['fDateVal','eDateVal'],
];

function resetForm() {
    document.getElementById('fId').value = '';
    ['fPrenom','fNom','fLieuNaiss','fFonction','fTel','fEmail','fCni','fLieuDeliv']
        .forEach(id => { const e = document.getElementById(id); if(e){e.value='';e.classList.remove('error');} });
    ['fDateNaiss','fDateDeliv','fDateVal']
        .forEach(id => { const e = document.getElementById(id); if(e) e.value=''; });
    ['fSexe','fAgence','fPharmacie','fDepot']
        .forEach(id => { const e = document.getElementById(id); if(e) e.value=''; });
    document.getElementById('fActif').checked = true;
    REQUIRED_FIELDS.forEach(([,eid]) => { const e=document.getElementById(eid); if(e) e.textContent=''; });
}

function openAddModal() {
    resetForm();
    document.getElementById('mfTitle').textContent = 'Nouveau professionnel';
    document.getElementById('mfSub').textContent   = 'Remplir les informations ci-dessous';
    document.getElementById('formOverlay').classList.add('open');
}

function openEditModal(id) {
    const p = ALL_DATA.find(x => x.id === id); if (!p) return;
    resetForm();
    document.getElementById('fId').value          = id;
    document.getElementById('mfTitle').textContent = 'Modifier le professionnel';
    document.getElementById('mfSub').textContent   = `${p.prenom} ${p.nom}`;
    document.getElementById('fPrenom').value       = p.prenom || '';
    document.getElementById('fNom').value          = p.nom || '';
    document.getElementById('fSexe').value         = p.sexe || '';
    document.getElementById('fDateNaiss').value    = p.date_naissance || '';
    document.getElementById('fLieuNaiss').value    = p.lieu_naissance || '';
    document.getElementById('fFonction').value     = p.fonction || '';
    document.getElementById('fAgence').value       = p.agence_id    || '';
    document.getElementById('fPharmacie').value    = p.pharmacie_id || '';
    document.getElementById('fDepot').value        = p.depot_id     || '';
    document.getElementById('fTel').value          = p.telephone    || '';
    document.getElementById('fEmail').value        = p.email        || '';
    document.getElementById('fCni').value          = p.numero_cni   || '';
    document.getElementById('fDateDeliv').value    = p.date_delivrance || '';
    document.getElementById('fLieuDeliv').value    = p.lieu_delivrance || '';
    document.getElementById('fDateVal').value      = p.date_validite   || '';
    document.getElementById('fActif').checked      = !!p.is_active;
    document.getElementById('formOverlay').classList.add('open');
}

function closeFormModal() { document.getElementById('formOverlay').classList.remove('open'); }

async function savePro() {
    let ok = true;
    REQUIRED_FIELDS.forEach(([fid, eid]) => {
        const el = document.getElementById(fid), er = document.getElementById(eid);
        if (!el?.value?.trim()) {
            if (er) er.textContent = 'Champ obligatoire';
            el?.classList.add('error'); ok = false;
        } else {
            if (er) er.textContent = '';
            el?.classList.remove('error');
        }
    });
    if (!ok) return;

    const id  = document.getElementById('fId').value;
    const btn = document.getElementById('btnSave');
    btn.disabled = true;
    btn.innerHTML = `<svg style="animation:spin .7s linear infinite;width:14px;height:14px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Enregistrement…`;

    const payload = {
        action:          id ? 'update' : 'create',
        prenom:          document.getElementById('fPrenom').value.trim(),
        nom:             document.getElementById('fNom').value.trim(),
        sexe:            document.getElementById('fSexe').value,
        date_naissance:  document.getElementById('fDateNaiss').value,
        lieu_naissance:  document.getElementById('fLieuNaiss').value.trim(),
        fonction:        document.getElementById('fFonction').value.trim(),
        agence_id:       document.getElementById('fAgence').value    || null,
        pharmacie_id:    document.getElementById('fPharmacie').value || null,
        depot_id:        document.getElementById('fDepot').value     || null,
        telephone:       document.getElementById('fTel').value.trim()    || null,
        email:           document.getElementById('fEmail').value.trim()  || null,
        numero_cni:      document.getElementById('fCni').value.trim()    || null,
        date_delivrance: document.getElementById('fDateDeliv').value,
        lieu_delivrance: document.getElementById('fLieuDeliv').value.trim(),
        date_validite:   document.getElementById('fDateVal').value,
        is_active:       document.getElementById('fActif').checked ? 1 : 0,
    };
    if (id) payload.id = parseInt(id);

    try {
        const res  = await fetch(API_BASE, {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            const d = data.data;
            d.categorie = detectCat(d.fonction || '');
            if (id) {
                const i = ALL_DATA.findIndex(x => x.id === parseInt(id));
                if (i !== -1) ALL_DATA[i] = d;
            } else {
                ALL_DATA.push(d);
            }
            closeFormModal();
            applyFilters();
            toast(id ? 'Professionnel modifié avec succès' : 'Professionnel ajouté avec succès', 'success');
        } else throw new Error(data.message || 'Erreur serveur');
    } catch (e) {
        toast(e.message || 'Erreur réseau', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg> Enregistrer`;
    }
}

/* ════════════════════════════════════════════════════════════
   MODAL SUPPRESSION
════════════════════════════════════════════════════════════ */
function openDelModal(id) {
    const p = ALL_DATA.find(x => x.id === id); if (!p) return;
    pendingDelId = id;
    document.getElementById('delMsg').textContent = `« ${p.prenom} ${p.nom} » — ${p.fonction || ''}`;
    document.getElementById('delOverlay').classList.add('open');
}
function closeDelModal() { document.getElementById('delOverlay').classList.remove('open'); pendingDelId = null; }

document.getElementById('btnConfirmDel').addEventListener('click', async function () {
    if (!pendingDelId) return;
    const btn = this; btn.disabled = true;
    btn.innerHTML = `<svg style="width:14px;height:14px;animation:spin .7s linear infinite" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Suppression…`;
    try {
        const res  = await fetch(API_BASE, {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ action:'delete', id:pendingDelId })
        });
        const data = await res.json();
        if (data.success) {
            const i = ALL_DATA.findIndex(x => x.id === pendingDelId);
            if (i !== -1) ALL_DATA.splice(i, 1);
            closeDelModal(); applyFilters();
            toast('Professionnel supprimé avec succès', 'success');
        } else throw new Error(data.message || 'Erreur');
    } catch (e) { closeDelModal(); toast(e.message || 'Erreur réseau', 'error'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = `<svg style="width:13px;height:13px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg> Supprimer`;
    }
});

/* ── Toast ── */
function toast(msg, type = 'default') {
    const w = document.getElementById('toastWrap'), el = document.createElement('div');
    const ok  = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`;
    const err = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`;
    el.className = `toast toast--${type}`;
    el.innerHTML = `${type==='success'?ok:type==='error'?err:''}${msg}`;
    w.appendChild(el);
    setTimeout(() => { el.classList.add('out'); el.addEventListener('animationend', ()=>el.remove()); }, 3500);
}

/* ── Fermeture (clic backdrop / Escape) ── */
document.getElementById('formOverlay').addEventListener('click', e => { if (e.target===document.getElementById('formOverlay')) closeFormModal(); });
document.getElementById('delOverlay').addEventListener('click',  e => { if (e.target===document.getElementById('delOverlay'))  closeDelModal(); });
document.addEventListener('keydown', e => { if (e.key==='Escape') { closeFormModal(); closeDelModal(); closeDrawer(); } });

/* ── Listeners filtres ── */
document.getElementById('searchInput').addEventListener('input', applyFilters);
['filterSexe','filterStatut'].forEach(id => document.getElementById(id).addEventListener('change', applyFilters));

/* ════════════════════════════════════════════════════════════
   INIT — Catégorie DÉLÉGUÉ par défaut
════════════════════════════════════════════════════════════ */
// Re-calcul catégorie côté JS (cohérent avec PHP)
ALL_DATA.forEach(p => { p.categorie = detectCat(p.fonction || ''); });

// currentCat = 'delegue' est déjà initialisé en haut
// L'onglet "Délégués médicaux" a déjà cat-tab--active dans le HTML
applyFilters();