// js/app.js

// ── Reloj en tiempo real ──
function updateClock() {
    const el = document.getElementById('reloj');
    const elDate = document.getElementById('reloj-fecha');
    if (!el) return;
    const now = new Date();
    const hh = String(now.getHours()).padStart(2,'0');
    const mm = String(now.getMinutes()).padStart(2,'0');
    const ss = String(now.getSeconds()).padStart(2,'0');
    el.textContent = `${hh}:${mm}:${ss}`;
    if (elDate) {
        const opts = { weekday:'long', year:'numeric', month:'long', day:'numeric' };
        elDate.textContent = now.toLocaleDateString('es-ES', opts);
    }
}
setInterval(updateClock, 1000);
updateClock();

// ── Fichar entrada/salida ──
function fichar(accion) {
    const btn = document.getElementById('btn-' + accion);
    if (btn) btn.disabled = true;

    const form = new FormData();
    form.append('action', accion);

    fetch('php/fichajes.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('mensaje-fichaje');
            if (!el) return;
            el.style.display = 'block';
            if (data.success) {
                const clase = data.tarde ? 'alert-warning' : 'alert-success';
                el.className = 'alert ' + clase;
                el.textContent = data.mensaje;
                // Recargar estado
                setTimeout(() => location.reload(), 2000);
            } else {
                el.className = 'alert alert-error';
                el.textContent = data.error;
                if (btn) btn.disabled = false;
            }
        })
        .catch(() => {
            const el = document.getElementById('mensaje-fichaje');
            if (el) { el.style.display='block'; el.className='alert alert-error'; el.textContent='Error de conexión'; }
            if (btn) btn.disabled = false;
        });
}

// ── Validación registro ──
function validarRegistro() {
    const nombre = document.getElementById('nombre')?.value.trim();
    const email  = document.getElementById('email')?.value.trim();
    const pass   = document.getElementById('password')?.value;
    const pass2  = document.getElementById('password2')?.value;
    const errEl  = document.getElementById('form-error');

    if (!nombre || nombre.length < 2) {
        showError(errEl, 'El nombre debe tener al menos 2 caracteres');
        return false;
    }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showError(errEl, 'Introduce un email válido');
        return false;
    }
    if (!pass || pass.length < 6) {
        showError(errEl, 'La contraseña debe tener al menos 6 caracteres');
        return false;
    }
    if (pass !== pass2) {
        showError(errEl, 'Las contraseñas no coinciden');
        return false;
    }
    return true;
}

function validarLogin() {
    const email = document.getElementById('email')?.value.trim();
    const pass  = document.getElementById('password')?.value;
    const errEl = document.getElementById('form-error');
    if (!email) { showError(errEl, 'Introduce tu email'); return false; }
    if (!pass)  { showError(errEl, 'Introduce tu contraseña'); return false; }
    return true;
}

function showError(el, msg) {
    if (!el) return;
    el.className = 'alert alert-error';
    el.style.display = 'block';
    el.textContent = msg;
}

// ── Tabs ──
function initTabs() {
    const tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            const target = document.getElementById(btn.dataset.tab);
            if (target) target.classList.add('active');
        });
    });
}
document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    // Auto-cargar datos del manager al abrir esa pestaña
    const managerBtn = document.querySelector('[data-tab="tab-manager"]');
    if (managerBtn) managerBtn.addEventListener('click', loadManagerData);
});

// ── Time Tracking ──
function ttAction(action) {
    const project_id = document.getElementById('sel-proyecto')?.value;
    const el = document.getElementById('msg-tt');
    const form = new FormData();
    form.append('action', action);
    if (project_id) form.append('project_id', project_id);

    fetch('php/time_tracking.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (!el) return;
            el.style.display = 'block';
            el.className = 'alert ' + (data.success ? 'alert-success' : 'alert-error');
            el.textContent = data.success ? data.mensaje : data.error;
            if (data.success) setTimeout(() => location.reload(), 1500);
        })
        .catch(() => {
            if (el) { el.style.display='block'; el.className='alert alert-error'; el.textContent='Error de conexión'; }
        });
}

// ── Manager Dashboard ──
function loadManagerData() {
    // Sesiones activas
    fetch('php/api_reports.php?type=active')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('active-sessions-body');
            if (!tbody) return;
            if (!data.active_sessions?.length) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--muted)">Ningún empleado activo ahora mismo</td></tr>';
                return;
            }
            tbody.innerHTML = data.active_sessions.map(s =>
                `<tr>
                    <td>${escHtml(s.employee)}</td>
                    <td>${escHtml(s.project)}</td>
                    <td>${s.start_time.slice(11,16)}</td>
                    <td style="font-family:var(--font-mono)">${s.hours_running}h</td>
                </tr>`
            ).join('');
        });

    // Lista roja
    fetch('php/api_reports.php?type=red_list')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('red-list-body');
            if (!tbody) return;
            if (!data.red_list?.length) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--muted)">✅ Sin incumplimientos hoy</td></tr>';
                return;
            }
            const labels = {
                late_arrival:        'Llegada tarde',
                early_exit:          'Salida anticipada',
                insufficient_hours:  'Horas insuficientes',
                no_clock_in:         'Sin fichar entrada'
            };
            tbody.innerHTML = data.red_list.map(r => {
                let detail = '';
                if (r.issue === 'late_arrival')       detail = `Entró ${(r.clock_in||'').slice(0,5)} · previsto ${(r.expected_in||'').slice(0,5)}`;
                if (r.issue === 'early_exit')         detail = `Salió ${(r.clock_out||'').slice(0,5)} · previsto ${(r.expected_out||'').slice(0,5)}`;
                if (r.issue === 'insufficient_hours') detail = `${r.hours_today}h de ${r.expected}h esperadas`;
                if (r.issue === 'no_clock_in')        detail = 'No ha registrado entrada';
                return `<tr class="tarde">
                    <td>${escHtml(r.employee)}</td>
                    <td><span class="badge badge-red">${labels[r.issue] || r.issue}</span></td>
                    <td style="color:var(--muted);font-size:0.85rem">${detail}</td>
                </tr>`;
            }).join('');
        });

    // Resumen de proyectos
    fetch('php/api_reports.php?type=projects')
        .then(r => r.json())
        .then(data => {
            const wrap = document.getElementById('project-report-wrap');
            if (!wrap || !data.projects) return;
            if (!data.projects.length) {
                wrap.innerHTML = '<p style="color:var(--muted)">No hay proyectos activos</p>';
                return;
            }
            wrap.innerHTML = data.projects.map(p => {
                const pct  = Math.min(p.percent_used, 100);
                const color = pct > 90 ? 'var(--red)' : pct > 70 ? 'var(--yellow)' : 'var(--green)';
                return `<div style="background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:1rem 1.25rem;margin-bottom:0.75rem">
                    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:0.5rem">
                        <strong>${escHtml(p.project)}</strong>
                        <span style="font-family:var(--font-mono);font-size:0.85rem;color:var(--muted)">${p.used_hours}h / ${p.contracted_hours}h</span>
                    </div>
                    <div style="background:var(--bg3);border-radius:4px;height:8px;overflow:hidden">
                        <div style="background:${color};height:100%;width:${pct}%;transition:width .5s"></div>
                    </div>
                    <div style="font-size:0.78rem;color:var(--muted);margin-top:0.35rem">
                        ${p.remaining_hours}h restantes &middot; ${p.percent_used}% consumido
                    </div>
                </div>`;
            }).join('');
        });
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
