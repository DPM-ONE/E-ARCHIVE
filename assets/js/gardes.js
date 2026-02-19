/* ═══════════════════════════════════════════════════════════
   gardes.js — DPM Archive
   Vue timeline (par mois) + vue tableau
   CRUD : ajout, édition, suppression via API
═══════════════════════════════════════════════════════════ */

/* ── État global ─────────────────────────────────────────── */
let filtered    = [...ALL_DATA];
let currentPage = 1;
const perPage   = 20;
let sortKey     = 'jour';
let sortDir     = 1;
let currentView = 'timeline';
let pendingDeleteId = null;

/* ── Mois ordonnés Fév → Jan ─────────────────────────────── */
const MONTH_ORDER = [2,3,4,5,6,7,8,9,10,11,12,1];
const MONTH_NAMES = {
    1:'Janvier',2:'Février',3:'Mars',4:'Avril',
    5:'Mai',6:'Juin',7:'Juillet',8:'Août',
    9:'Septembre',10:'Octobre',11:'Novembre',12:'Décembre'
};
const DOW_NAMES = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
const MON_SHORT = ['jan','fév','mar','avr','mai','jun','jul','aoû','sep','oct','nov','déc'];

/* Couleurs de groupe */
function groupBadge(groupeId, groupeLabel) {
    const cls = 'group-badge--' + ((groupeId - 1) % 4 + 1);
    return `<span class="group-badge ${cls}">${groupeLabel || '—'}</span>`;
}

/* ── Date helpers ────────────────────────────────────────── */
function parseDate(str) {
    const [y,m,d] = str.split('-').map(Number);
    return new Date(y, m-1, d);
}
function formatFull(str) {
    const d = parseDate(str);
    return `${DOW_NAMES[d.getDay()]} ${d.getDate()} ${MONTH_NAMES[d.getMonth()+1]} ${d.getFullYear()}`;
}
function isThisWeek(str) {
    const now = new Date();
    const dow = now.getDay() === 0 ? 6 : now.getDay() - 1;
    const mon = new Date(now); mon.setDate(now.getDate() - dow); mon.setHours(0,0,0,0);
    const sun = new Date(mon); sun.setDate(mon.getDate() + 6); sun.setHours(23,59,59,999);
    const d = parseDate(str);
    return d >= mon && d <= sun;
}
function isPast(str) {
    const today = new Date(); today.setHours(0,0,0,0);
    return parseDate(str) < today;
}

/* ── Remplir le filtre des mois disponibles ─────────────── */
function populateMoisFilter() {
    const sel = document.getElementById('filterMois');
    const seen = new Set();
    ALL_DATA.forEach(g => {
        const d = parseDate(g.jour);
        const key = `${d.getFullYear()}-${d.getMonth()+1}`;
        if (!seen.has(key)) {
            seen.add(key);
            const opt = document.createElement('option');
            opt.value = key;
            opt.textContent = `${MONTH_NAMES[d.getMonth()+1]} ${d.getFullYear()}`;
            sel.appendChild(opt);
        }
    });
}

/* ── Filtres ─────────────────────────────────────────────── */
function applyFilters() {
    const search  = document.getElementById('searchInput').value.toLowerCase().trim();
    const grpId   = parseInt(document.getElementById('filterGroupe').value) || 0;
    const deptId  = parseInt(document.getElementById('filterDept').value) || 0;
    const moisVal = document.getElementById('filterMois').value;

    filtered = ALL_DATA.filter(g => {
        if (search) {
            const hay = [g.groupe_libelle, g.dept_libelle, formatFull(g.jour)].join(' ').toLowerCase();
            if (!hay.includes(search)) return false;
        }
        if (grpId  && g.groupe_id !== grpId) return false;
        if (deptId && g.departement_id !== deptId) return false;
        if (moisVal) {
            const d = parseDate(g.jour);
            const key = `${d.getFullYear()}-${d.getMonth()+1}`;
            if (key !== moisVal) return false;
        }
        return true;
    });

    filtered.sort((a, b) => {
        let av = a[sortKey] || '', bv = b[sortKey] || '';
        if (typeof av === 'string') { av = av.toLowerCase(); bv = bv.toLowerCase(); }
        if (av < bv) return -sortDir;
        if (av > bv) return  sortDir;
        return 0;
    });

    currentPage = 1;
    document.getElementById('resultsCount').innerHTML =
        `<strong>${filtered.length}</strong> garde${filtered.length !== 1 ? 's' : ''}`;

    if (currentView === 'timeline') renderTimeline();
    else { renderTable(); renderPagination(); }
}

function resetAll() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterGroupe').value = '';
    document.getElementById('filterDept').value = '';
    document.getElementById('filterMois').value = '';
    applyFilters();
}

/* ── Vue ─────────────────────────────────────────────────── */
function setView(v) {
    currentView = v;
    document.getElementById('viewTimeline').style.display = v === 'timeline' ? '' : 'none';
    document.getElementById('viewTable').style.display    = v === 'table'    ? '' : 'none';
    document.getElementById('btnViewTimeline').classList.toggle('view-btn--active', v === 'timeline');
    document.getElementById('btnViewTable').classList.toggle('view-btn--active', v === 'table');
    applyFilters();
}

/* ── TIMELINE ────────────────────────────────────────────── */
function renderTimeline() {
    const container = document.getElementById('viewTimeline');

    if (!filtered.length) {
        container.innerHTML = `<div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <h3>Aucune garde trouvée</h3><p>Modifiez vos filtres ou ajoutez une nouvelle garde</p>
        </div>`;
        return;
    }

    /* Grouper par (année, mois) */
    const byMonthDept = {};
    filtered.forEach(g => {
        const d = parseDate(g.jour);
        const yr = d.getFullYear();
        const mo = d.getMonth() + 1;
        const mk = `${yr}-${mo}`;
        if (!byMonthDept[mk]) byMonthDept[mk] = { yr, mo, depts: {} };
        const dk = g.departement_id || 0;
        if (!byMonthDept[mk].depts[dk]) byMonthDept[mk].depts[dk] = { label: g.dept_libelle || 'Tous départements', gardes: [] };
        byMonthDept[mk].depts[dk].gardes.push(g);
    });

    /* Trier les clés mois dans l'ordre Fév → Jan */
    const allKeys = Object.keys(byMonthDept);
    allKeys.sort((a, b) => {
        const [ya, ma] = a.split('-').map(Number);
        const [yb, mb] = b.split('-').map(Number);
        /* mapping cyclique sur l'ordre Fév-Jan */
        const ia = MONTH_ORDER.indexOf(ma) + (ya - 2026) * 12;
        const ib = MONTH_ORDER.indexOf(mb) + (yb - 2026) * 12;
        return ia - ib;
    });

    let html = '';
    allKeys.forEach(mk => {
        const { yr, mo, depts } = byMonthDept[mk];
        const totalMonth = Object.values(depts).reduce((s, d) => s + d.gardes.length, 0);

        html += `<div class="month-block" id="mb_${mk}">
            <div class="month-header" onclick="toggleMonth('${mk}')">
                <svg class="month-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                <span class="month-name">${MONTH_NAMES[mo]}</span>
                <span class="month-year">${yr}</span>
                <span class="month-count">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:11px;height:11px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    ${totalMonth} garde${totalMonth > 1 ? 's' : ''}
                </span>
            </div>
            <div class="month-body" id="mbody_${mk}">`;

        Object.values(depts).forEach(dept => {
            html += `<div class="dept-section">
                <div class="dept-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/></svg>
                    ${dept.label}
                </div>
                <div class="garde-cards">`;

            dept.gardes.forEach(g => {
                const d    = parseDate(g.jour);
                const day  = d.getDate();
                const mon  = MON_SHORT[d.getMonth()];
                const week = isThisWeek(g.jour);
                const past = isPast(g.jour);

                html += `<div class="garde-card ${week ? 'is-this-week' : ''} ${past ? 'is-past' : ''}" onclick="event.stopPropagation()">
                    ${week ? '<span class="week-dot" title="En garde cette semaine"></span>' : ''}
                    <div class="garde-card__date-box">
                        <span class="garde-card__day">${day}</span>
                        <span class="garde-card__month">${mon}</span>
                    </div>
                    <div class="garde-card__info">
                        <div class="garde-card__dow">${formatFull(g.jour)}</div>
                        <div class="garde-card__group">${groupBadge(g.groupe_id, g.groupe_libelle)}</div>
                    </div>
                    <div class="garde-card__actions">
                        <button class="action-btn" title="Modifier" onclick="openEditModal(${g.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="action-btn action-btn--danger" title="Supprimer" onclick="openDelModal(${g.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                        </button>
                    </div>
                </div>`;
            });

            html += `</div></div>`;
        });

        html += `</div></div>`;
    });

    container.innerHTML = html;

    /* Ajuster la height des month-body */
    allKeys.forEach(mk => {
        const body = document.getElementById('mbody_' + mk);
        if (body) body.style.maxHeight = body.scrollHeight + 'px';
    });
}

function toggleMonth(mk) {
    const block = document.getElementById('mb_' + mk);
    const body  = document.getElementById('mbody_' + mk);
    if (!block || !body) return;
    const isCollapsed = block.classList.contains('collapsed');
    if (isCollapsed) {
        block.classList.remove('collapsed');
        body.style.maxHeight = body.scrollHeight + 'px';
        body.style.opacity   = '1';
    } else {
        block.classList.add('collapsed');
        body.style.maxHeight = '0';
        body.style.opacity   = '0';
    }
}

/* ── TABLE ───────────────────────────────────────────────── */
function sortBy(key) {
    sortKey = key; sortDir = sortKey === key ? sortDir * -1 : 1; sortKey = key;
    applyFilters();
}

function renderTable() {
    const tbody = document.getElementById('tableBody');
    const empty = document.getElementById('emptyState');
    const start = (currentPage - 1) * perPage;
    const page  = filtered.slice(start, start + perPage);

    if (!filtered.length) { tbody.innerHTML = ''; empty.style.display = ''; return; }
    empty.style.display = 'none';

    tbody.innerHTML = page.map(g => {
        const week = isThisWeek(g.jour);
        return `<tr>
            <td>
                <div class="td-date-main">${formatFull(g.jour)}</div>
                <div class="td-date-sub">${g.jour}</div>
            </td>
            <td>${groupBadge(g.groupe_id, g.groupe_libelle)}${week ? ' <span style="font-size:.7rem;color:var(--green);font-weight:600">● cette semaine</span>' : ''}</td>
            <td>${g.dept_libelle || '<span style="color:var(--ink-soft);font-style:italic">—</span>'}</td>
            <td>
                <div class="td-actions">
                    <button class="action-btn" title="Modifier" onclick="openEditModal(${g.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <button class="action-btn action-btn--danger" title="Supprimer" onclick="openDelModal(${g.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function renderPagination() {
    const pag = document.getElementById('pagination');
    if (!pag) return;
    const totalPages = Math.ceil(filtered.length / perPage);
    if (totalPages <= 1) { pag.innerHTML = ''; return; }
    let html = `<button class="pag-btn" onclick="goPage(${currentPage-1})" ${currentPage===1?'disabled':''}>‹</button>`;
    for (let i = 1; i <= totalPages; i++) {
        if (i===1||i===totalPages||Math.abs(i-currentPage)<=2)
            html += `<button class="pag-btn ${i===currentPage?'active':''}" onclick="goPage(${i})">${i}</button>`;
        else if (Math.abs(i-currentPage)===3) html += `<span class="pag-ellipsis">…</span>`;
    }
    html += `<button class="pag-btn" onclick="goPage(${currentPage+1})" ${currentPage===totalPages?'disabled':''}>›</button>`;
    pag.innerHTML = html;
}

function goPage(n) {
    const total = Math.ceil(filtered.length / perPage);
    if (n < 1 || n > total) return;
    currentPage = n; renderTable(); renderPagination();
}

/* ── Modal Ajout / Édition ───────────────────────────────── */
function openAddModal() {
    document.getElementById('editId').value = '';
    document.getElementById('modalTitle').textContent = 'Nouvelle garde';
    document.getElementById('modalSub').textContent   = 'Planifier un jour de garde';
    document.getElementById('inputJour').value    = '';
    document.getElementById('inputGroupe').value  = '';
    document.getElementById('inputDept').value    = '';
    document.getElementById('errJour').textContent   = '';
    document.getElementById('errGroupe').textContent = '';
    document.getElementById('inputJour').classList.remove('error');
    document.getElementById('inputGroupe').classList.remove('error');
    document.getElementById('formOverlay').classList.add('open');
}

function openEditModal(id) {
    const g = ALL_DATA.find(x => x.id === id);
    if (!g) return;
    document.getElementById('editId').value = id;
    document.getElementById('modalTitle').textContent = 'Modifier la garde';
    document.getElementById('modalSub').textContent   = formatFull(g.jour);
    document.getElementById('inputJour').value    = g.jour;
    document.getElementById('inputGroupe').value  = g.groupe_id || '';
    document.getElementById('inputDept').value    = g.departement_id || '';
    document.getElementById('errJour').textContent   = '';
    document.getElementById('errGroupe').textContent = '';
    document.getElementById('inputJour').classList.remove('error');
    document.getElementById('inputGroupe').classList.remove('error');
    document.getElementById('formOverlay').classList.add('open');
}

function closeFormModal() {
    document.getElementById('formOverlay').classList.remove('open');
}

async function saveGarde() {
    const id      = document.getElementById('editId').value;
    const jour    = document.getElementById('inputJour').value.trim();
    const grpId   = document.getElementById('inputGroupe').value;
    const deptId  = document.getElementById('inputDept').value;

    let valid = true;
    if (!jour) {
        document.getElementById('errJour').textContent = 'La date est obligatoire';
        document.getElementById('inputJour').classList.add('error');
        valid = false;
    } else { document.getElementById('errJour').textContent = ''; document.getElementById('inputJour').classList.remove('error'); }

    if (!grpId) {
        document.getElementById('errGroupe').textContent = 'Le groupe est obligatoire';
        document.getElementById('inputGroupe').classList.add('error');
        valid = false;
    } else { document.getElementById('errGroupe').textContent = ''; document.getElementById('inputGroupe').classList.remove('error'); }

    if (!valid) return;

    const btn = document.getElementById('btnSave');
    btn.disabled = true;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite;width:14px;height:14px"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Enregistrement…`;

    const payload = { action: id ? 'update' : 'create', jour, groupe_id: parseInt(grpId), departement_id: deptId ? parseInt(deptId) : null };
    if (id) payload.id = parseInt(id);

    try {
        const res  = await fetch(API_BASE, { method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if (data.success) {
            if (id) {
                const idx = ALL_DATA.findIndex(x => x.id === parseInt(id));
                if (idx !== -1) ALL_DATA[idx] = data.data;
            } else {
                ALL_DATA.push(data.data);
                ALL_DATA.sort((a,b) => a.jour.localeCompare(b.jour));
            }
            closeFormModal();
            applyFilters();
            toast(id ? 'Garde modifiée avec succès' : 'Garde ajoutée avec succès', 'success');
        } else {
            throw new Error(data.message || 'Erreur serveur');
        }
    } catch(e) {
        toast(e.message || 'Erreur réseau', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg> Enregistrer`;
    }
}

/* ── Modal suppression ───────────────────────────────────── */
function openDelModal(id) {
    const g = ALL_DATA.find(x => x.id === id);
    if (!g) return;
    pendingDeleteId = id;
    document.getElementById('delMsg').textContent = `Garde du ${formatFull(g.jour)} — ${g.groupe_libelle || 'Groupe inconnu'}`;
    document.getElementById('delOverlay').classList.add('open');
}

function closeDelModal() {
    document.getElementById('delOverlay').classList.remove('open');
    pendingDeleteId = null;
}

document.getElementById('btnConfirmDel').addEventListener('click', async function () {
    if (!pendingDeleteId) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Suppression…`;

    try {
        const res  = await fetch(API_BASE, { method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'delete', id: pendingDeleteId }) });
        const data = await res.json();
        if (data.success) {
            const idx = ALL_DATA.findIndex(x => x.id === pendingDeleteId);
            if (idx !== -1) ALL_DATA.splice(idx, 1);
            closeDelModal();
            applyFilters();
            toast('Garde supprimée avec succès', 'success');
        } else throw new Error(data.message || 'Erreur');
    } catch(e) {
        closeDelModal();
        toast(e.message || 'Erreur réseau', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg> Supprimer`;
    }
});

/* ── Toast ───────────────────────────────────────────────── */
function toast(msg, type='default') {
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    const iconOk  = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`;
    const iconErr = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`;
    el.className = `toast toast--${type}`;
    el.innerHTML = `${type==='success'?iconOk:(type==='error'?iconErr:'')}${msg}`;
    wrap.appendChild(el);
    setTimeout(() => { el.classList.add('out'); el.addEventListener('animationend', () => el.remove()); }, 3500);
}

/* ── Fermeture modals ────────────────────────────────────── */
document.getElementById('formOverlay').addEventListener('click', function(e) { if (e.target===this) closeFormModal(); });
document.getElementById('delOverlay').addEventListener('click', function(e)  { if (e.target===this) closeDelModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeFormModal(); closeDelModal(); } });

/* ── Écouter les filtres ─────────────────────────────────── */
['searchInput'].forEach(id => document.getElementById(id)?.addEventListener('input', applyFilters));
['filterGroupe','filterDept','filterMois'].forEach(id => document.getElementById(id)?.addEventListener('change', applyFilters));

/* ── Init ────────────────────────────────────────────────── */
populateMoisFilter();
applyFilters();