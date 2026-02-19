/* ═══════════════════════════════════════════════════════════
   fosa.js — DPM Archive
   Architecture miroir de pharmacies.js :
   - Table avec tri et pagination
   - Drawer latéral pour les détails
   - Redirect vers edit-fosa.php pour modification
   - Modal de confirmation simple pour suppression
═══════════════════════════════════════════════════════════ */

/* ── État global ─────────────────────────────────────────── */
let filtered       = [...ALL_DATA];
let currentPage    = 1;
let perPage        = 15;
let sortKey        = 'nom_fosa';
let sortDir        = 1; // 1 = asc, -1 = desc
let activeDrawerId = null;
let pendingDeleteId= null;

/* ── Helpers ─────────────────────────────────────────────── */
function initials(str) {
    return (str || '?').trim().split(/\s+/).slice(0, 2)
           .map(w => w[0]).join('').toUpperCase() || '?';
}

function v(val, fallback = '—') {
    return val && String(val).trim() ? String(val).trim() : fallback;
}

/* ── Tri ─────────────────────────────────────────────────── */
function sortBy(key) {
    if (sortKey === key) {
        sortDir *= -1;
    } else {
        sortKey = key;
        sortDir = 1;
    }
    document.querySelectorAll('thead th').forEach(th => th.classList.remove('sort-asc', 'sort-desc'));
    const thMap = {
        'nom_fosa':        'th_nom',
        'nom_responsable': 'th_resp',
        'departement':     'th_dept',
    };
    if (thMap[key]) {
        const th = document.getElementById(thMap[key]);
        if (th) th.classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');
    }
    applyFilters();
}

/* ── Filtres ─────────────────────────────────────────────── */
function applyFilters() {
    const search = document.getElementById('searchInput').value.toLowerCase().trim();
    const deptId = parseInt(document.getElementById('filterDept').value) || 0;

    /* DS : on regarde la select visible */
    const dsSel  = document.getElementById('filterDS');
    const dsAll  = document.getElementById('filterDSAll');
    const dsId   = deptId
        ? (parseInt(dsSel.value)  || 0)
        : (parseInt(dsAll.value) || 0);

    filtered = ALL_DATA.filter(p => {
        if (search) {
            const hay = [p.nom_fosa, p.prenom_responsable, p.nom_responsable,
                         p.adresse, p.telephone, p.departement, p.district_sanitaire]
                        .join(' ').toLowerCase();
            if (!hay.includes(search)) return false;
        }
        if (deptId && p.departement_id !== deptId) return false;
        if (dsId   && p.district_sanitaire_id !== dsId) return false;
        return true;
    });

    /* Tri */
    filtered.sort((a, b) => {
        let av = a[sortKey], bv = b[sortKey];
        if (typeof av === 'string') { av = av.toLowerCase(); bv = bv.toLowerCase(); }
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
    document.getElementById('searchInput').value    = '';
    document.getElementById('filterDept').value     = '';
    document.getElementById('filterDS').value       = '';
    document.getElementById('filterDS').style.display = 'none';
    document.getElementById('filterDSAll').value    = '';
    document.getElementById('filterDSAll').style.display = '';
    applyFilters();
}

/* Logique filtre DS selon département sélectionné */
document.getElementById('filterDept').addEventListener('change', function () {
    const deptId = parseInt(this.value) || 0;
    const dsSel  = document.getElementById('filterDS');
    const dsAll  = document.getElementById('filterDSAll');

    dsSel.innerHTML = '<option value="">Tous les districts</option>';

    if (!deptId) {
        dsSel.style.display = 'none';
        dsAll.style.display = '';
        dsAll.value = '';
        applyFilters();
        return;
    }

    const matching = ALL_DS.filter(d => d.departement_id == deptId);
    matching.forEach(d => {
        dsSel.innerHTML += `<option value="${d.id}">${d.nom_ds}</option>`;
    });
    dsSel.style.display = '';
    dsAll.style.display = 'none';
    dsAll.value = '';
    applyFilters();
});

['searchInput','filterDS','filterDSAll'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener(id === 'searchInput' ? 'input' : 'change', applyFilters);
});

/* ── Rendu table ─────────────────────────────────────────── */
function renderTable() {
    const tbody = document.getElementById('tableBody');
    const empty = document.getElementById('emptyState');
    const start = (currentPage - 1) * perPage;
    const page  = filtered.slice(start, start + perPage);

    if (!filtered.length) {
        tbody.innerHTML = '';
        empty.style.display = '';
        return;
    }
    empty.style.display = 'none';

    tbody.innerHTML = page.map(p => {
        const resp = [p.prenom_responsable, p.nom_responsable].filter(Boolean).join(' ') || '—';
        return `<tr onclick="openDrawer(${p.id})">
            <td>
                <div class="td-name">${p.nom_fosa}</div>
                ${p.district_sanitaire ? `<div class="td-sub td-ds">${p.district_sanitaire}</div>` : ''}
            </td>
            <td>
                <div>${resp}</div>
                ${p.telephone ? `<div class="td-sub">${p.telephone}</div>` : ''}
            </td>
            <td>
                <div class="td-loc">${v(p.departement)}</div>
                ${p.adresse ? `<div class="td-sub" style="font-size:.75rem">${p.adresse}</div>` : ''}
            </td>
            <td>${p.telephone ? `<span style="font-size:.82rem">${p.telephone}</span>` : '<span style="color:#9CA3AF;font-size:.8rem">—</span>'}</td>
            <td>
                <div class="td-actions" onclick="event.stopPropagation()">
                    ${CAN_WRITE ? `
                    <button class="action-btn" title="Modifier" onclick="location.href='edit-fosa.php?id=${p.id}'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <button class="action-btn action-btn--danger" title="Supprimer" onclick="openDelModal(${p.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                    </button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

/* ── Pagination ──────────────────────────────────────────── */
function renderPagination() {
    const pag        = document.getElementById('pagination');
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

/* ── Drawer détail ───────────────────────────────────────── */
function openDrawer(id) {
    const p = ALL_DATA.find(x => x.id === id);
    if (!p) return;
    activeDrawerId = id;

    const resp = [p.prenom_responsable, p.nom_responsable].filter(Boolean).join(' ') || '—';
    document.getElementById('drawerAvatar').textContent = initials(p.nom_fosa);
    document.getElementById('drawerTitle').textContent  = p.nom_fosa;
    document.getElementById('drawerSub').textContent    = resp;
    if (document.getElementById('btnDrawerEdit'))
        document.getElementById('btnDrawerEdit').href = `edit-fosa.php?id=${p.id}`;

    document.getElementById('drawerBody').innerHTML = `
        <div class="drawer-section">
            <div class="drawer-section-title">Responsable & Contact</div>
            ${p.prenom_responsable || p.nom_responsable ? `<div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <div>
                    <div class="drawer-row-label">Responsable</div>
                    <div class="drawer-row-val">${resp}</div>
                </div>
            </div>` : ''}
            ${p.telephone ? `<div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.68 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6.29 6.29l.78-.78a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <div>
                    <div class="drawer-row-label">Téléphone</div>
                    <div class="drawer-row-val">${p.telephone}</div>
                </div>
            </div>` : ''}
        </div>

        <div class="drawer-section">
            <div class="drawer-section-title">Localisation</div>
            <div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/></svg>
                <div>
                    <div class="drawer-row-label">Département</div>
                    <div class="drawer-row-val">${v(p.departement)}</div>
                </div>
            </div>
            ${p.district_sanitaire ? `<div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <div>
                    <div class="drawer-row-label">District sanitaire</div>
                    <div class="drawer-row-val">${p.district_sanitaire}</div>
                </div>
            </div>` : ''}
            ${p.adresse ? `<div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <div>
                    <div class="drawer-row-label">Adresse / Coordonnées</div>
                    <div class="drawer-row-val">${p.adresse}</div>
                </div>
            </div>` : ''}
        </div>

        ${p.created_at ? `<div class="drawer-section">
            <div class="drawer-section-title">Métadonnées</div>
            <div class="drawer-row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <div>
                    <div class="drawer-row-label">Enregistrée le</div>
                    <div class="drawer-row-val">${new Date(p.created_at).toLocaleDateString('fr-FR')}</div>
                </div>
            </div>
        </div>` : ''}
    `;

    document.getElementById('drawerBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    document.getElementById('drawerBackdrop').classList.remove('open');
    document.body.style.overflow = '';
    activeDrawerId = null;
}

document.getElementById('drawerBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeDrawer();
});

const btnDrawerDel = document.getElementById('btnDrawerDelete');
if (btnDrawerDel) {
    btnDrawerDel.addEventListener('click', () => {
        const id = activeDrawerId;
        if (!id) return;
        closeDrawer();
        setTimeout(() => openDelModal(id), 260);
    });
}

/* ── Modal suppression ───────────────────────────────────── */
function openDelModal(id) {
    const p = ALL_DATA.find(x => x.id === id);
    if (!p) return;
    pendingDeleteId = Number(id);
    document.getElementById('delMsg').textContent = `« ${p.nom_fosa} » sera définitivement supprimée.`;
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
            toast('Formation sanitaire supprimée avec succès', 'success');
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

/* ── Init ────────────────────────────────────────────────── */
applyFilters();