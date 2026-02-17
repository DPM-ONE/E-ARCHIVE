/**
 * SETTINGS - Gestion des utilisateurs
 * assets/js/settings.js
 * Thème XP - DPM Archive
 */

'use strict';

// ============================================================
// SVG ICONS (password toggle)
// ============================================================
const SVG_EYE = `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="2"
     stroke-linecap="round" stroke-linejoin="round">
    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
    <circle cx="12" cy="12" r="3"/>
</svg>`;

const SVG_EYE_CLOSE = `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="2"
     stroke-linecap="round" stroke-linejoin="round">
    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8
             a18.45 18.45 0 0 1 5.06-5.94"/>
    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8
             a18.5 18.5 0 0 1-2.16 3.19"/>
    <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
    <line x1="1" y1="1" x2="23" y2="23"/>
</svg>`;

// ============================================================
// PERMISSIONS PAR RÔLE (par défaut)
// ============================================================
const ROLE_PERMISSIONS = {
    super_admin: {
        all: { view: true, create: true, edit: true, delete: true, export: true }
    },
    admin: {
        view: true, create: true, edit: true, delete: false, export: true
    },
    archiviste: {
        view: true, create: true, edit: true, delete: false, export: true
    },
    lecteur: {
        view: true, create: false, edit: false, delete: false, export: false
    }
};

const MODULES = [
    'courriers', 'visas', 'pharmacies', 'agences', 'depots',
    'laboratoires', 'medicaments', 'delegues', 'formations',
    'pharmaciens', 'depositaires', 'rapports', 'agents'
];

const ACTIONS = ['view', 'create', 'edit', 'delete', 'export'];

const MODULE_LABELS = {
    courriers: 'Courriers', visas: 'Visas', pharmacies: 'Pharmacies',
    agences: 'Agences', depots: 'Dépôts', laboratoires: 'Laboratoires',
    medicaments: 'Médicaments', delegues: 'Délégués médicaux',
    formations: 'Formations sanitaires', pharmaciens: 'Pharmaciens',
    depositaires: 'Dépositaires', rapports: 'Rapports', agents: 'Agents DPM'
};

const ACTION_LABELS = {
    view: 'Voir', create: 'Créer', edit: 'Modifier',
    delete: 'Supprimer', export: 'Exporter'
};


// ============================================================
// INITIALISATION
// ============================================================
document.addEventListener('DOMContentLoaded', function () {

    // Password toggles
    initPasswordToggles();

    // Recherche & filtres
    document.getElementById('searchUsers')?.addEventListener('input', filterTable);
    document.getElementById('filterRole')?.addEventListener('change', filterTable);
    document.getElementById('filterStatus')?.addEventListener('change', filterTable);
    document.getElementById('btnResetFilters')?.addEventListener('click', resetFilters);

    // Bouton créer
    document.getElementById('btnAddUser')?.addEventListener('click', openCreateModal);

    // Formulaire user
    document.getElementById('formUser')?.addEventListener('submit', handleFormSubmit);

    // Toggle actif label
    document.getElementById('inputIsActive')?.addEventListener('change', function () {
        document.getElementById('toggleLabel').textContent =
            this.checked ? 'Compte actif' : 'Compte inactif';
    });

    // Fermeture modales au clic extérieur
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
});


// ============================================================
// PASSWORD TOGGLE
// ============================================================
function initPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(toggle => {
        toggle.innerHTML = SVG_EYE;
        toggle.setAttribute('title', 'Afficher le mot de passe');

        toggle.addEventListener('click', function () {
            const targetId = this.dataset.target;
            const input    = document.getElementById(targetId);
            if (!input) return;

            if (input.type === 'password') {
                input.type     = 'text';
                this.innerHTML = SVG_EYE_CLOSE;
                this.setAttribute('title', 'Masquer le mot de passe');
            } else {
                input.type     = 'password';
                this.innerHTML = SVG_EYE;
                this.setAttribute('title', 'Afficher le mot de passe');
            }
        });
    });
}


// ============================================================
// FILTRES & RECHERCHE
// ============================================================
function filterTable() {
    const search = (document.getElementById('searchUsers')?.value || '').toLowerCase();
    const role   = document.getElementById('filterRole')?.value  || '';
    const status = document.getElementById('filterStatus')?.value ?? '';

    let visibleCount = 0;

    document.querySelectorAll('.user-row').forEach(row => {
        const rowSearch = row.dataset.search || '';
        const rowRole   = row.dataset.role   || '';
        const rowStatus = row.dataset.status  ?? '';

        const matchSearch = !search || rowSearch.includes(search);
        const matchRole   = !role   || rowRole === role;
        const matchStatus = status === '' || rowStatus === status;

        const visible = matchSearch && matchRole && matchStatus;
        row.style.display = visible ? '' : 'none';
        if (visible) visibleCount++;
    });

    const badge = document.getElementById('userCount');
    if (badge) badge.textContent = visibleCount + ' utilisateur' + (visibleCount > 1 ? 's' : '');
}

function resetFilters() {
    document.getElementById('searchUsers').value  = '';
    document.getElementById('filterRole').value   = '';
    document.getElementById('filterStatus').value = '';
    filterTable();
}


// ============================================================
// MODALES
// ============================================================
function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
}


// ============================================================
// CRÉER UN UTILISATEUR
// ============================================================
function openCreateModal() {
    // Reset formulaire
    document.getElementById('formUser').reset();
    document.getElementById('userId').value      = '';
    document.getElementById('userAction').value  = 'create';
    document.getElementById('modalUserTitle').textContent = 'Nouvel utilisateur';
    document.getElementById('passwordGroup').style.display = '';
    document.getElementById('inputPassword').required = true;
    document.getElementById('toggleLabel').textContent = 'Compte actif';

    // Permissions par défaut (lecteur)
    updatePermissionsFromRole('lecteur');

    // Réinitialiser password toggles
    initPasswordToggles();

    openModal('modalUser');
}


// ============================================================
// MODIFIER UN UTILISATEUR
// ============================================================
function editUser(user) {
    document.getElementById('userAction').value  = 'update';
    document.getElementById('userId').value      = user.id;
    document.getElementById('modalUserTitle').textContent = 'Modifier — ' + user.username;

    // Remplir les champs
    document.getElementById('inputUsername').value  = user.username || '';
    document.getElementById('inputEmail').value     = user.email    || '';
    document.getElementById('inputRole').value      = user.role     || 'lecteur';
    document.getElementById('inputIsActive').checked = user.is_active == 1;
    document.getElementById('toggleLabel').textContent = user.is_active == 1 ? 'Compte actif' : 'Compte inactif';

    // Mot de passe optionnel en modification
    document.getElementById('passwordGroup').style.display = 'block';
    document.getElementById('inputPassword').required = false;
    document.getElementById('inputPassword').placeholder = 'Laisser vide pour ne pas changer';

    // Charger les permissions
    let permissions = {};
    if (user.permissions) {
        try {
            permissions = typeof user.permissions === 'string'
                ? JSON.parse(user.permissions)
                : user.permissions;
        } catch (e) { permissions = {}; }
    }
    applyPermissionsToForm(permissions);

    // Réinitialiser password toggles
    initPasswordToggles();

    openModal('modalUser');
}


// ============================================================
// SOUMETTRE LE FORMULAIRE (Créer ou Modifier)
// ============================================================
function handleFormSubmit(e) {
    e.preventDefault();

    if (!validateUserForm()) return;

    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const original  = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="preloader__spinner" style="width:18px;height:18px;border-width:2px;"></div> Enregistrement...';

    // Construire les permissions JSON
    const permissions = collectPermissions();
    formData.set('permissions', JSON.stringify(permissions));

    $.ajax({
        url: '../api/users.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (res) {
            if (res.success) {
                closeModal('modalUser');
                showToast('success', res.message || 'Opération réussie');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast('error', res.message || 'Une erreur est survenue');
                submitBtn.disabled = false;
                submitBtn.innerHTML = original;
            }
        },
        error: function () {
            showToast('error', 'Erreur de connexion au serveur');
            submitBtn.disabled = false;
            submitBtn.innerHTML = original;
        }
    });
}


// ============================================================
// VALIDATION FORMULAIRE
// ============================================================
function validateUserForm() {
    let valid = true;

    // Username
    const username = document.getElementById('inputUsername').value.trim();
    const errUser  = document.getElementById('errUsername');
    if (!username || !/^[a-z0-9._-]{3,50}$/i.test(username)) {
        errUser.textContent = 'Nom d\'utilisateur invalide (3-50 caractères)';
        document.getElementById('inputUsername').classList.add('error');
        valid = false;
    } else {
        errUser.textContent = '';
        document.getElementById('inputUsername').classList.remove('error');
    }

    // Email
    const email    = document.getElementById('inputEmail').value.trim();
    const errEmail = document.getElementById('errEmail');
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errEmail.textContent = 'Email invalide';
        document.getElementById('inputEmail').classList.add('error');
        valid = false;
    } else {
        errEmail.textContent = '';
        document.getElementById('inputEmail').classList.remove('error');
    }

    // Mot de passe (création uniquement)
    const action   = document.getElementById('userAction').value;
    const password = document.getElementById('inputPassword').value;
    const errPwd   = document.getElementById('errPassword');

    if (action === 'create' || (action === 'update' && password !== '')) {
        if (password.length < 8) {
            errPwd.textContent = 'Minimum 8 caractères';
            document.getElementById('inputPassword').classList.add('error');
            valid = false;
        } else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(password)) {
            errPwd.textContent = 'Doit contenir majuscule, minuscule et chiffre';
            document.getElementById('inputPassword').classList.add('error');
            valid = false;
        } else {
            errPwd.textContent = '';
            document.getElementById('inputPassword').classList.remove('error');
        }
    }

    return valid;
}


// ============================================================
// PERMISSIONS
// ============================================================
function collectPermissions() {
    const permissions = {};
    document.querySelectorAll('.perm-input').forEach(input => {
        const mod    = input.dataset.module;
        const action = input.dataset.action;
        if (!permissions[mod]) permissions[mod] = {};
        permissions[mod][action] = input.checked;
    });
    return permissions;
}

function applyPermissionsToForm(permissions) {
    document.querySelectorAll('.perm-input').forEach(input => {
        const mod    = input.dataset.module;
        const action = input.dataset.action;
        input.checked = permissions[mod]?.[action] === true;
    });
}

function updatePermissionsFromRole(role) {
    let permsMap = {};

    if (role === 'super_admin') {
        MODULES.forEach(mod => {
            permsMap[mod] = { view: true, create: true, edit: true, delete: true, export: true };
        });
    } else if (role === 'admin') {
        MODULES.forEach(mod => {
            permsMap[mod] = { view: true, create: true, edit: true, delete: false, export: true };
        });
    } else if (role === 'archiviste') {
        MODULES.forEach(mod => {
            const canEdit = ['courriers', 'visas', 'pharmacies', 'depots', 'laboratoires', 'rapports'].includes(mod);
            permsMap[mod] = { view: true, create: canEdit, edit: canEdit, delete: false, export: canEdit };
        });
    } else {
        // Lecteur
        MODULES.forEach(mod => {
            permsMap[mod] = { view: true, create: false, edit: false, delete: false, export: false };
        });
    }

    applyPermissionsToForm(permsMap);
}

function toggleAllPermissions(state) {
    document.querySelectorAll('.perm-input').forEach(input => {
        input.checked = state;
    });
}


// ============================================================
// VOIR LES PERMISSIONS
// ============================================================
function viewPermissions(userId, username, permissions) {
    document.getElementById('modalPermTitle').textContent = 'Permissions de ' + username;

    let perms = {};
    if (permissions) {
        try {
            perms = typeof permissions === 'string'
                ? JSON.parse(permissions)
                : permissions;
        } catch (e) { perms = {}; }
    }

    let html = '<div class="perm-view-grid">';

    MODULES.forEach(mod => {
        const modPerms = perms[mod] || {};
        html += `
            <div class="perm-view-module">
                <div class="perm-view-module__title">${MODULE_LABELS[mod]}</div>
                <div class="perm-view-module__actions">`;

        ACTIONS.forEach(action => {
            const allowed = modPerms[action] === true;
            html += `<span class="perm-tag perm-tag--${allowed ? 'allowed' : 'denied'}">
                ${allowed ? '✓' : '✗'} ${ACTION_LABELS[action]}
            </span>`;
        });

        html += `</div></div>`;
    });

    html += '</div>';

    document.getElementById('modalPermBody').innerHTML = html;
    openModal('modalPermissions');
}


// ============================================================
// RÉINITIALISER MOT DE PASSE
// ============================================================
function resetPassword(userId, username) {
    document.getElementById('resetUserId').value  = userId;
    document.getElementById('resetUserMsg').textContent =
        `Définir un nouveau mot de passe pour le compte "${username}".`;

    // Reset les champs
    document.getElementById('newPassword').value        = '';
    document.getElementById('newPasswordConfirm').value = '';

    // Réinit password toggles
    setTimeout(initPasswordToggles, 50);

    openModal('modalResetPwd');
}

function confirmResetPassword() {
    const userId  = document.getElementById('resetUserId').value;
    const pwd     = document.getElementById('newPassword').value;
    const pwdConf = document.getElementById('newPasswordConfirm').value;

    if (!pwd || pwd.length < 8) {
        showToast('error', 'Le mot de passe doit contenir au moins 8 caractères');
        return;
    }

    if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(pwd)) {
        showToast('error', 'Doit contenir majuscule, minuscule et chiffre');
        return;
    }

    if (pwd !== pwdConf) {
        showToast('error', 'Les mots de passe ne correspondent pas');
        return;
    }

    $.ajax({
        url: '../api/users.php',
        type: 'POST',
        data: { action: 'reset_password', user_id: userId, new_password: pwd },
        dataType: 'json',
        success: function (res) {
            if (res.success) {
                closeModal('modalResetPwd');
                showToast('success', 'Mot de passe réinitialisé avec succès');
            } else {
                showToast('error', res.message || 'Erreur lors de la réinitialisation');
            }
        },
        error: function () {
            showToast('error', 'Erreur de connexion au serveur');
        }
    });
}


// ============================================================
// ACTIVER / DÉSACTIVER
// ============================================================
function toggleUser(userId, currentStatus, username) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const action    = newStatus ? 'activer' : 'désactiver';

    if (!confirm(`Voulez-vous ${action} le compte de "${username}" ?`)) return;

    $.ajax({
        url: '../api/users.php',
        type: 'POST',
        data: { action: 'toggle_status', user_id: userId, is_active: newStatus },
        dataType: 'json',
        success: function (res) {
            if (res.success) {
                showToast('success', res.message || 'Statut mis à jour');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('error', res.message || 'Erreur');
            }
        },
        error: function () {
            showToast('error', 'Erreur de connexion au serveur');
        }
    });
}


// ============================================================
// DÉBLOQUER UN COMPTE
// ============================================================
function unlockUser(userId, username) {
    if (!confirm(`Débloquer le compte de "${username}" ?`)) return;

    $.ajax({
        url: '../api/users.php',
        type: 'POST',
        data: { action: 'unlock', user_id: userId },
        dataType: 'json',
        success: function (res) {
            if (res.success) {
                showToast('success', 'Compte débloqué avec succès');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('error', res.message || 'Erreur');
            }
        },
        error: function () {
            showToast('error', 'Erreur de connexion au serveur');
        }
    });
}


// ============================================================
// SUPPRIMER UN UTILISATEUR
// ============================================================
function deleteUser(userId, username) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserMsg').textContent =
        `Vous êtes sur le point de supprimer le compte "${username}". Cette action est irréversible.`;
    openModal('modalDelete');
}

function confirmDelete() {
    const userId = document.getElementById('deleteUserId').value;

    $.ajax({
        url: '../api/users.php',
        type: 'POST',
        data: { action: 'delete', user_id: userId },
        dataType: 'json',
        success: function (res) {
            if (res.success) {
                closeModal('modalDelete');
                showToast('success', 'Utilisateur supprimé');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('error', res.message || 'Erreur lors de la suppression');
            }
        },
        error: function () {
            showToast('error', 'Erreur de connexion au serveur');
        }
    });
}


// ============================================================
// TOAST NOTIFICATIONS
// ============================================================
const TOAST_ICONS = {
    success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
              </svg>`,
    error:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
              </svg>`,
    warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>`,
    info:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>`
};

function showToast(type, message, duration = 4000) {
    const container = document.getElementById('toastContainer');
    const id        = 'toast-' + Date.now();

    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.id = id;
    toast.innerHTML = `
        ${TOAST_ICONS[type] || TOAST_ICONS.info}
        <span class="toast__msg">${message}</span>
        <button class="toast__close" onclick="removeToast('${id}')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>`;

    container.appendChild(toast);

    setTimeout(() => removeToast(id), duration);
}

function removeToast(id) {
    const toast = document.getElementById(id);
    if (toast) {
        toast.style.animation = 'fadeOut 0.25s ease-out forwards';
        setTimeout(() => toast.remove(), 250);
    }
}