<?php
// admin.php — Panel d'administració complet
// Accés restringit: només rol 'admin'
session_start();
require_once 'php/auth.php';
require_once 'php/config.php';

// ── Protecció de ruta: backend ─────────────────────────────────────────────
requireLogin();
if (!isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    header('Location: dashboard.php');
    exit;
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin · Fichajes</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ── Estils específics del panel admin ── */
        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 768px) { .admin-grid { grid-template-columns: 1fr; } }

        .admin-card {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
        }
        .admin-card h3 {
            font-family: var(--font-mono);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-bottom: 1.25rem;
        }
        .inline-form { display: flex; flex-direction: column; gap: 0.75rem; }
        .inline-form .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .btn-sm {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0.3rem 0.75rem; border-radius: 4px; font-size: 0.8rem;
            font-family: var(--font-sans); font-weight: 500; cursor: pointer; border: none;
            transition: all 0.2s;
        }
        .btn-sm-red    { background: rgba(248,113,113,.15); color: var(--red); }
        .btn-sm-red:hover { background: var(--red); color: #0f1117; }
        .stat-row {
            display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem;
        }
        .stat-box {
            background: var(--bg2); border: 1px solid var(--border); border-radius: 8px;
            padding: 1rem 1.5rem; text-align: center; flex: 1; min-width: 120px;
        }
        .stat-box .num { font-family: var(--font-mono); font-size: 2rem; font-weight: 600; color: var(--accent); }
        .stat-box .lbl { font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .progress-bar-wrap { background: var(--bg3); border-radius: 4px; height: 6px; margin-top: 0.4rem; overflow: hidden; }
        .progress-bar { height: 100%; transition: width .5s; }
        .msg-box { padding: 0.6rem 0.9rem; border-radius: 6px; font-size: 0.85rem; margin-bottom: 0.75rem; display: none; border-left: 3px solid; }
        .msg-ok  { background: rgba(52,211,153,.1); border-color: var(--green); color: var(--green); }
        .msg-err { background: rgba(248,113,113,.1); border-color: var(--red);   color: var(--red);   }
    </style>
</head>
<body>

<!-- Navbar (reutilitzem l'estil existent) -->
<nav class="navbar">
    <div class="container">
        <span class="navbar-brand">fichajes<span>_admin</span></span>
        <div class="navbar-user">
            <a href="dashboard.php" style="font-size:0.875rem; color:var(--muted)">← Tornar al dashboard</a>
            <span class="badge badge-blue">admin</span>
            <form method="POST" action="logout.php" style="margin:0">
                <button type="submit" class="btn-logout">Sortir</button>
            </form>
        </div>
    </div>
</nav>

<div class="container">

    <div class="page-header">
        <h1>Panel d'administració</h1>
        <p>Gestió completa d'usuaris i projectes · <?= htmlspecialchars($user['nombre']) ?></p>
    </div>

    <!-- Estadístiques ràpides -->
    <div class="stat-row" id="stats-row">
        <div class="stat-box"><div class="num" id="stat-users">—</div><div class="lbl">Usuaris</div></div>
        <div class="stat-box"><div class="num" id="stat-admins">—</div><div class="lbl">Admins</div></div>
        <div class="stat-box"><div class="num" id="stat-projects">—</div><div class="lbl">Projectes</div></div>
        <div class="stat-box"><div class="num" id="stat-hours">—</div><div class="lbl">Hores usades</div></div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" data-tab="tab-users">👥 Usuaris</button>
        <button class="tab-btn" data-tab="tab-projects">📁 Projectes</button>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         TAB: USUARIS
    ════════════════════════════════════════════════════════════ -->
    <div id="tab-users" class="tab-content active">
        <div class="admin-grid">

            <!-- Formulari crear usuari -->
            <div class="admin-card">
                <h3>➕ Crear usuari nou</h3>
                <div id="msg-create-user" class="msg-box"></div>
                <div class="inline-form">
                    <div class="form-group" style="margin:0">
                        <label>Nom complet</label>
                        <input type="text" id="u-nombre" placeholder="Anna García">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label>Email</label>
                        <input type="email" id="u-email" placeholder="anna@empresa.com">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label>Contrasenya</label>
                        <input type="password" id="u-password" placeholder="Mínim 6 caràcters">
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="margin:0">
                            <label>Rol</label>
                            <select id="u-rol">
                                <option value="empleado">empleado</option>
                                <option value="admin">admin</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0">
                            <label>Hora entrada</label>
                            <input type="time" id="u-hora-entrada" value="09:00">
                        </div>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label>Hora sortida</label>
                        <input type="time" id="u-hora-salida" value="17:00">
                    </div>
                    <button class="btn btn-primary" onclick="createUser()">Crear usuari</button>
                </div>
            </div>

            <!-- Info panel -->
            <div class="admin-card" style="display:flex;flex-direction:column;justify-content:center;gap:1rem">
                <h3>ℹ️ Informació</h3>
                <p style="font-size:0.875rem; color:var(--muted); line-height:1.7">
                    Els usuaris creats des d'aquí es creen directament amb la contrasenya xifrada (bcrypt).<br><br>
                    Per canviar l'horari d'un empleat, utilitza el botó "Editar horari" a la taula.<br><br>
                    <span style="color:var(--red)">⚠️ No pots eliminar el teu propi compte.</span><br><br>
                    <span style="color:var(--yellow)">⚠️ Eliminar un usuari elimina també tots els seus fitxatges i entrades de temps.</span>
                </p>
            </div>
        </div>

        <!-- Taula d'usuaris -->
        <p class="section-title">Tots els usuaris</p>
        <div id="msg-users" class="msg-box"></div>
        <div class="table-wrap">
            <table id="users-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Entrada</th>
                        <th>Sortida</th>
                        <th>Registrat</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody id="users-tbody">
                    <tr><td colspan="8" style="text-align:center;color:var(--muted)">Carregant…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         TAB: PROJECTES
    ════════════════════════════════════════════════════════════ -->
    <div id="tab-projects" class="tab-content">
        <div class="admin-grid">

            <!-- Formulari crear projecte -->
            <div class="admin-card">
                <h3>➕ Crear projecte nou</h3>
                <div id="msg-create-project" class="msg-box"></div>
                <div class="inline-form">
                    <div class="form-group" style="margin:0">
                        <label>Nom del projecte</label>
                        <input type="text" id="p-nombre" placeholder="Projecte X">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label>Descripció (opcional)</label>
                        <input type="text" id="p-desc" placeholder="Breu descripció...">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label>Hores contractades</label>
                        <input type="number" id="p-hours" placeholder="100" min="0" step="0.5" value="0">
                    </div>
                    <button class="btn btn-primary" onclick="createProject()">Crear projecte</button>
                </div>
            </div>

            <!-- Info panel -->
            <div class="admin-card" style="display:flex;flex-direction:column;justify-content:center;gap:1rem">
                <h3>ℹ️ Informació</h3>
                <p style="font-size:0.875rem; color:var(--muted); line-height:1.7">
                    Els projectes creats aquí apareixeran immediatament al selector del dashboard dels empleats.<br><br>
                    Les hores contractades s'utilitzen per calcular el % de consum al panel Manager.<br><br>
                    <span style="color:var(--red)">⚠️ No es pot eliminar un projecte si hi ha empleats treballant-hi en aquest moment.</span><br><br>
                    Eliminar un projecte <strong>no elimina</strong> les entrades de temps històriques.
                </p>
            </div>
        </div>

        <!-- Taula de projectes -->
        <p class="section-title">Tots els projectes</p>
        <div id="msg-projects" class="msg-box"></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom</th>
                        <th>Descripció</th>
                        <th>H. Contractades</th>
                        <th>H. Usades</th>
                        <th>% Consum</th>
                        <th>Creat per</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody id="projects-tbody">
                    <tr><td colspan="8" style="text-align:center;color:var(--muted)">Carregant…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /container -->

<script src="js/app.js"></script>
<script>
// ── Admin panel JS ──────────────────────────────────────────────────────────

const currentUserId = <?= (int)$_SESSION['usuario_id'] ?>;

// Carregar dades en iniciar
document.addEventListener('DOMContentLoaded', () => {
    loadUsers();
    loadProjects();

    // Carregar projectes quan s'obre la pestanya
    document.querySelector('[data-tab="tab-projects"]')
        ?.addEventListener('click', loadProjects);
});

// ── Helpers ──
function showMsg(elId, text, isError = false) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.textContent  = text;
    el.className    = 'msg-box ' + (isError ? 'msg-err' : 'msg-ok');
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

function adminFetch(body) {
    const form = new FormData();
    Object.entries(body).forEach(([k, v]) => form.append(k, v));
    return fetch('php/admin_api.php', { method: 'POST', body: form }).then(r => r.json());
}

// ── USUARIS ──────────────────────────────────────────────────────────────────

function loadUsers() {
    fetch('php/admin_api.php?resource=users')
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showMsg('msg-users', data.error, true); return; }
            renderUsers(data.users);
            // Actualitzar estadístiques
            document.getElementById('stat-users').textContent  = data.users.length;
            document.getElementById('stat-admins').textContent = data.users.filter(u => u.rol === 'admin').length;
        });
}

function renderUsers(users) {
    const tbody = document.getElementById('users-tbody');
    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--muted)">Sense usuaris</td></tr>';
        return;
    }
    tbody.innerHTML = users.map(u => {
        const isSelf   = u.id == currentUserId;
        const rolBadge = u.rol === 'admin'
            ? '<span class="badge badge-blue">admin</span>'
            : '<span class="badge badge-green">empleado</span>';
        const deleteBtn = isSelf
            ? '<span style="font-size:0.75rem;color:var(--muted)">Compte actual</span>'
            : `<button class="btn-sm btn-sm-red" onclick="deleteUser(${u.id}, '${escHtml(u.nombre)}')">Eliminar</button>`;
        const date = u.created_at ? u.created_at.slice(0,10) : '—';
        return `<tr>
            <td style="font-family:var(--font-mono);color:var(--muted)">${u.id}</td>
            <td>${escHtml(u.nombre)} ${isSelf ? '<span style="font-size:0.7rem;color:var(--accent)">(tu)</span>' : ''}</td>
            <td style="font-size:0.85rem">${escHtml(u.email)}</td>
            <td>${rolBadge}</td>
            <td style="font-family:var(--font-mono)">${u.hora_entrada?.slice(0,5)||'—'}</td>
            <td style="font-family:var(--font-mono)">${u.hora_salida?.slice(0,5)||'—'}</td>
            <td style="font-size:0.8rem;color:var(--muted)">${date}</td>
            <td>${deleteBtn}</td>
        </tr>`;
    }).join('');
}

function createUser() {
    const nombre   = document.getElementById('u-nombre').value.trim();
    const email    = document.getElementById('u-email').value.trim();
    const password = document.getElementById('u-password').value;
    const rol      = document.getElementById('u-rol').value;
    const h_entra  = document.getElementById('u-hora-entrada').value;
    const h_surt   = document.getElementById('u-hora-salida').value;

    if (!nombre || !email || !password) {
        showMsg('msg-create-user', 'Nom, email i contrasenya són obligatoris', true); return;
    }
    if (password.length < 6) {
        showMsg('msg-create-user', 'La contrasenya ha de tenir mínim 6 caràcters', true); return;
    }

    adminFetch({ resource:'users', nombre, email, password, rol,
                 hora_entrada: h_entra, hora_salida: h_surt })
        .then(data => {
            if (data.success) {
                showMsg('msg-create-user', '✅ ' + data.mensaje);
                // Netejar formulari
                ['u-nombre','u-email','u-password'].forEach(id => document.getElementById(id).value = '');
                loadUsers();
            } else {
                showMsg('msg-create-user', data.error, true);
            }
        });
}

function deleteUser(id, nombre) {
    if (!confirm(`Eliminar l'usuari "${nombre}"?\nAquesta acció eliminarà també tots els seus fitxatges i entrades de temps.`)) return;
    adminFetch({ resource:'users', _method:'DELETE', id })
        .then(data => {
            showMsg('msg-users', data.success ? '✅ ' + data.mensaje : data.error, !data.success);
            if (data.success) loadUsers();
        });
}

// ── PROJECTES ─────────────────────────────────────────────────────────────────

function loadProjects() {
    fetch('php/admin_api.php?resource=projects')
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showMsg('msg-projects', data.error, true); return; }
            renderProjects(data.projects);
            // Estadística hores
            const totalHours = data.projects.reduce((s, p) => s + parseFloat(p.used_hours || 0), 0);
            document.getElementById('stat-projects').textContent = data.projects.length;
            document.getElementById('stat-hours').textContent    = totalHours.toFixed(1) + 'h';
        });
}

function renderProjects(projects) {
    const tbody = document.getElementById('projects-tbody');
    if (!projects.length) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--muted)">Sense projectes</td></tr>';
        return;
    }
    tbody.innerHTML = projects.map(p => {
        const pct   = p.contracted_hours > 0
            ? Math.min(Math.round((p.used_hours / p.contracted_hours) * 100), 100) : 0;
        const color = pct > 90 ? 'var(--red)' : pct > 70 ? 'var(--yellow)' : 'var(--green)';
        const progressBar = `
            <div>${pct}%</div>
            <div class="progress-bar-wrap">
                <div class="progress-bar" style="background:${color};width:${pct}%"></div>
            </div>`;
        return `<tr>
            <td style="font-family:var(--font-mono);color:var(--muted)">${p.id}</td>
            <td><strong>${escHtml(p.nombre)}</strong></td>
            <td style="font-size:0.82rem;color:var(--muted)">${escHtml(p.description || '—')}</td>
            <td style="font-family:var(--font-mono)">${p.contracted_hours}h</td>
            <td style="font-family:var(--font-mono)">${p.used_hours}h</td>
            <td style="min-width:100px">${progressBar}</td>
            <td style="font-size:0.82rem;color:var(--muted)">${escHtml(p.created_by_name || '—')}</td>
            <td><button class="btn-sm btn-sm-red" onclick="deleteProject(${p.id}, '${escHtml(p.nombre)}')">Eliminar</button></td>
        </tr>`;
    }).join('');
}

function createProject() {
    const nombre = document.getElementById('p-nombre').value.trim();
    const desc   = document.getElementById('p-desc').value.trim();
    const hours  = document.getElementById('p-hours').value;

    if (!nombre) {
        showMsg('msg-create-project', 'El nom del projecte és obligatori', true); return;
    }

    adminFetch({ resource:'projects', nombre, description: desc, contracted_hours: hours })
        .then(data => {
            if (data.success) {
                showMsg('msg-create-project', '✅ ' + data.mensaje);
                document.getElementById('p-nombre').value = '';
                document.getElementById('p-desc').value   = '';
                document.getElementById('p-hours').value  = '0';
                loadProjects();
            } else {
                showMsg('msg-create-project', data.error, true);
            }
        });
}

function deleteProject(id, nombre) {
    if (!confirm(`Eliminar el projecte "${nombre}"?`)) return;
    adminFetch({ resource:'projects', _method:'DELETE', id })
        .then(data => {
            showMsg('msg-projects', data.success ? '✅ ' + data.mensaje : data.error, !data.success);
            if (data.success) loadProjects();
        });
}
</script>
</body>
</html>