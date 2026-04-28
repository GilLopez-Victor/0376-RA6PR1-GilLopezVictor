async function loadUsers() {
    const res = await fetch('php/admin_users.php');
    const users = await res.json();

    document.getElementById('users').innerHTML =
        users.map(u => `
            <div>
                ${u.username} (${u.role})
                <button onclick="deleteUser(${u.id})">X</button>
            </div>
        `).join('');
}

async function createUser() {
    await fetch('php/admin_users.php', {
        method: 'POST',
        body: JSON.stringify({
            username: u_name.value,
            email: u_email.value,
            password: u_pass.value,
            role: u_role.value
        })
    });
    loadUsers();
}

async function deleteUser(id) {
    await fetch(`php/admin_users.php?id=${id}`, {
        method: 'DELETE'
    });
    loadUsers();
}

async function loadProjects() {
    const res = await fetch('php/api_projects.php');
    const projects = await res.json();

    document.getElementById('projects').innerHTML =
        projects.map(p => `
            <div>
                ${p.name}
                <button onclick="deleteProject(${p.id})">X</button>
            </div>
        `).join('');
}

async function createProject() {
    await fetch('php/admin_projects.php', {
        method: 'POST',
        body: JSON.stringify({
            name: p_name.value,
            description: p_desc.value
        })
    });
    loadProjects();
}

async function deleteProject(id) {
    await fetch(`php/admin_projects.php?id=${id}`, {
        method: 'DELETE'
    });
    loadProjects();
}

loadUsers();
loadProjects();