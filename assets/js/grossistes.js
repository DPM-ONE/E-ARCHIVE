/* ═══════════════════════════════════════════════════════════
   grossistes.js — DPM Archive
═══════════════════════════════════════════════════════════ */

let filtered    = [...ALL_DATA];
let currentPage = 1;
let perPage     = 15;
let sortKey     = 'nom_grossiste';
let sortDir     = 1;
let activeDrawerId = null;
let pendingDeleteId = null;

/* ── Helpers ─────────────────────────────────────────────── */
function initials(str) {
    return (str || '?').trim().split(/\s+/).slice(0, 2)
           .map(w => w[0]).join('').toUpperCase() || '?';
}

function v(val, fallback = '—') {
    return val && String(val).trim() ? String(val).trim() : fallback;
}

function locLabel(g) {
    const parts = [];
    if (g.arrondissement) parts.push(g.arrondissement);
    if (g.district)       parts.push(g.district);
    return parts.join(' · ') || '—';
}

/* ── Tri ─────────────────────────────────────────────────── */
function sortBy(key) {
    if (sortKey === key) {
        sortDir *= -1;
    } else {
        sortKey = key;
        sortDir = 1;
    }
    document.querySelectorAll('thead th').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
    });
    const thMap = {
        'nom_grossiste': 'th_nom',
        'responsable':   'th_resp',
        'box_rangement': 'th_box',
        'is_actif':      'th_stat',
    };
    if (thMap[key]) {
        const th = document.getElementById(thMap[key]);
        if (th) th.classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');
    }
    applyFilters();
}

/* ── Filtres ─────────────────────────────────────────────── */
function applyFilters() {
    const search  = document.getElementById('searchInput').value.toLowerCase().trim();
    const deptId  = parseInt(document.getElementById('filterDept').value) || 0;
    const arrId   = parseInt(document.getElementById('filterArr').value) || 0;
    const distId  = parseInt(document.getElementById('filterDist').value) || 0;
    const statut  = document.getElementById('filterStatut').value;

    filtered = ALL_DATA.filter(g => {
        if (search) {
            const hay = [g.nom_grossiste, g.responsable, g.adresse,
                         g.quartier, g.telephone, g.email].join(' ').toLowerCase();
            if (!hay.includes(search)) return false;
        }
        if (deptId  && g.departement_id !== deptId) return false;
        if (arrId   && g.arrondissement_id !== arrId) return false;
        if (distId  && g.district_id !== distId) return false;
        if (statut === 'actif'   && !g.is_actif) return false;
        if (statut === 'inactif' && g.is_actif)  return false;
        return true;
    });

    filtered.sort((a, b) => {
        let av = a[sortKey], bv = b[sortKey];
        if (typeof av === 'boolean') { av = av ? 1 : 0; bv = bv ? 1 : 0; }
        if (typeof av === 'string')  { av = av.toLowerCase(); bv = bv.toLowerCase(); }
        if (av < bv) return -sortDir;
        if (av > bv) return  sortDir;
        return 0;
    });

    currentPage = 1;
    renderTable();
    renderPagination();
    document.getElementById('resultsCount').innerHTML =
        `<strong>${filtered.length}</strong> résultat${filtered.length !== 1 ? 's' : ''}`;
}

function resetAll() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterDept').value = '';
    document.getElementById('filterArr').value = '';
    document.getElementById('filterDist').value = '';
    document.getElementById('filterStatut').value = '';
    document.getElementById('filterArr').style.display = 'none';
    document.getElementById('filterDist').style.display = 'none';
    applyFilters();
}

document.getElementById('filterDept').addEventListener('change', function () {
    const deptId = parseInt(this.value) || 0;
    const hasArr = DEPTS_WITH_ARR.includes(deptId);
    const arrSel  = document.getElementById('filterArr');
    const distSel = document.getElementById('filterDist');

    arrSel.innerHTML  = '<option value="">Tous arrondissements</option>';
    distSel.innerHTML = '<option value="">Tous districts</option>';
    arrSel.style.display  = 'none';
    distSel.style.display = 'none';
    arrSel.value  = '';
    distSel.value = '';

    if (!deptId) { applyFilters(); return; }

    if (hasArr) {
        ALL_ARRS.filter(a => a.departement_id === deptId).forEach(a => {
            arrSel.innerHTML += `<option value="${a.id}">${a.libelle}</option>`;
        });
        arrSel.style.display = '';
    } else {
        ALL_DISTS.filter(d => d.departement_id === deptId).forEach(d => {
            distSel.innerHTML += `<option value="${d.id}">${d.libelle}</option>`;
        });
        distSel.style.display = '';
    }
    applyFilters();
});

['searchInput','filterArr','filterDist','filterStatut'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener(id === 'searchInput' ? 'input' : 'change', applyFilters);
});

/* ── Rendu table ─────────────────────────────────────────── */
function renderTable() {
    const tbody   = document.getElementById('tableBody');
    const empty   = document.getElementById('emptyState');
    const start   = (currentPage - 1) * perPage;
    const page    = filtered.slice(start, start + perPage);

    if (!filtered.length) {
        tbody.innerHTML = '';
        empty.style.display = '';
        return;
    }
    empty.style.display = 'none';

    tbody.innerHTML = page.map(g => {
        const loc    = locLabel(g);
        const actifB = g.is_actif
            ? '<span class="badge badge--actif">Actif</span>'
            : '<span class="badge badge--inactif">Inactif</span>';

        return `<tr onclick="openDrawer(${g.id})">
            <td>
                <div class="td-name">${g.nom_grossiste}</div>
                <div class="td-sub">${v(g.telephone)}</div>
            </td>
            <td>
                <div>${g.responsable}</div>
                <div class="td-sub">${v(g.email)}</div>
            </td>
            <td>
                <div class="td-loc">${v(g.quartier)}</div>
                <div class="td-loc-dept">${v(g.departement)}${loc !== '—' ? ' · ' + loc : ''}</div>
            </td>
            <td>${v(g.box_rangement)}</td>
            <td>${actifB}</td>
            <td>
                <div class="td-actions" onclick="event.stopPropagation()">
                    <button class="action-btn" title="Modifier"
                        onclick="location.href='edit-grossiste.php?id=${g.id}'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <button class="action-btn action-btn--danger" title="Supprimer"
                        onclick="openDelModal(${g.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

/* ── Pagination ──────────────────────────────────────────── */
function renderPagination() {
    const pag       = document.getElementById('pagination');
    const totalPages = Math.ceil(filtered.length / perPage);
    if (totalPages <= 1) { pag.innerHTML = ''; return; }

    let html = `<button class="pag-btn" onclick="goPage(${currentPage-1})" ${currentPage===1?'disabled':''}>‹</button>`;
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || Math.abs(i - currentPage) <= 2) {
            html += `<button class="pag-btn ${i===currentPage?'active':''}" onclick="goPage(${i})">${i}</button>`;
        } else if (Math.abs(i - currentPage) === 3) {
            html += `<span class="pag-ellipsis">…</span>`;
        }
    }
    html += `<button class="pag-btn" onclick="goPage(${currentPage+1})" ${currentPage===totalPages?'disabled':''}>›</button>`;
    pag.innerHTML = html;
}

function goPage(n) {
    const total = Math.ceil(filtered.length / perPage);
    if (n < 1 || n > total) return;
    currentPage = n;
    renderTable();
    renderPagination();
    document.querySelector('.table-wrap')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function changePerPage(val) {
    perPage = parseInt(val) || 15;
    currentPage = 1;
    renderTable();
    renderPagination();
}

/* ── Drawer ───────────────────────────────────────────────── */
function openDrawer(id) {
    const g = ALL_DATA.find(x => x.id === id);
    if (!g) return;
    activeDrawerId = id;

    document.getElementById('drawerAvatar').textContent = initials(g.nom_grossiste);
    document.getElementById('drawerTitle').textContent  = g.nom_grossiste;
    document.getElementById('drawerSub').textContent    = g.responsable;
    document.getElementById('btnDrawerEdit').href       = `edit-grossiste.php?id=${g.id}`;

    document.getElementById('drawerBody').innerHTML = `
        <div class="drawer-section">
            <div class="drawer-section-title">Contact</div>
            <div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.68 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6.29 6.29l.78-.78a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <div>
                    <div class="drawer-row-label">Téléphone</div>
                    <div class="drawer-row-val">${v(g.telephone)}</div>
                </div>
            </div>
            ${g.email ? `<div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <div><div class="drawer-row-label">Email</div><div class="drawer-row-val">${g.email}</div></div>
            </div>` : ''}
        </div>

        <div class="drawer-section">
            <div class="drawer-section-title">Localisation</div>
            <div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <div>
                    <div class="drawer-row-label">Adresse</div>
                    <div class="drawer-row-val">${v(g.adresse)}</div>
                    <div class="drawer-row-val">${v(g.quartier)}</div>
                </div>
            </div>
            <div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/></svg>
                <div>
                    <div class="drawer-row-label">Département</div>
                    <div class="drawer-row-val">${v(g.departement)}</div>
                    ${g.arrondissement ? `<div class="drawer-row-val">${g.arrondissement}</div>` : ''}
                    ${g.district       ? `<div class="drawer-row-val">${g.district}</div>` : ''}
                </div>
            </div>
        </div>

        <div class="drawer-section">
            <div class="drawer-section-title">Archive</div>
            ${g.box_rangement ? `<div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                <div><div class="drawer-row-label">Box rangement</div><div class="drawer-row-val">${g.box_rangement}</div></div>
            </div>` : ''}
            ${g.zone_archive ? `<div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                <div><div class="drawer-row-label">Zone archive</div><div class="drawer-row-val">${g.zone_archive}</div></div>
            </div>` : ''}
            <div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <div>
                    <div class="drawer-row-label">Statut</div>
                    <div class="drawer-row-val">${g.is_actif
                        ? '<span class="badge badge--actif">Actif</span>'
                        : '<span class="badge badge--inactif">Inactif</span>'}</div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('drawerBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    document.getElementById('drawerBackdrop').classList.remove('open');
    document.body.style.overflow = '';
    activeDrawerId = null;
}

document.getElementById('drawerBackdrop').onclick = null;
document.getElementById('drawerBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeDrawer();
});

document.getElementById('btnDrawerDelete').addEventListener('click', () => {
    const id = activeDrawerId;
    if (!id) return;
    closeDrawer();
    setTimeout(() => openDelModal(id), 260);
});

/* ── Modal suppression ───────────────────────────────────── */
function openDelModal(id) {
    const g = ALL_DATA.find(x => x.id === id);
    if (!g) return;
    pendingDeleteId = Number(id);
    document.getElementById('delMsg').textContent = `« ${g.nom_grossiste} » sera définitivement supprimé.`;
    document.getElementById('delOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDelModal() {
    document.getElementById('delOverlay').classList.remove('open');
    document.body.style.overflow = '';
    pendingDeleteId = null;
}

document.getElementById('btnConfirmDel').addEventListener('click', async function () {
    if (!pendingDeleteId) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Suppression…`;

    try {
        const res  = await fetch(API_BASE, {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/json' },
            body:        JSON.stringify({ action: 'delete', id: pendingDeleteId }),
        });
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch(_) {
            throw new Error('Réponse serveur invalide — voir console F12');
        }

        if (data.success) {
            const idx = ALL_DATA.findIndex(x => x.id === pendingDeleteId);
            if (idx !== -1) ALL_DATA.splice(idx, 1);
            closeDelModal();
            applyFilters();
            toast('Grossiste supprimé avec succès', 'success');
        } else {
            throw new Error(data.message || 'Erreur lors de la suppression');
        }
    } catch (e) {
        closeDelModal();
        toast(e.message || 'Erreur réseau, réessayez', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg> Supprimer`;
    }
});

document.getElementById('delOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeDelModal();
});

/* ── Toast ───────────────────────────────────────────────── */
function toast(msg, type = 'default') {
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    const iconOk  = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`;
    const iconErr = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`;
    el.className = `toast toast--${type}`;
    el.innerHTML = `${type === 'success' ? iconOk : (type === 'error' ? iconErr : '')}${msg}`;
    wrap.appendChild(el);
    setTimeout(() => {
        el.classList.add('out');
        el.addEventListener('animationend', () => el.remove());
    }, 3500);
}

applyFilters();