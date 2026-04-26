# fichajes_app — Control de Horarios Laborales

Aplicación PHP + MySQL para gestionar fichajes de empleados con control de puntualidad.

---

## Estructura del proyecto

```
fichajes_app/
├── index.php          → Redirección automática
├── login.php          → Inicio de sesión
├── register.php       → Registro de empleados
├── dashboard.php      → Panel principal (fichar, historial, admin)
├── logout.php         → Cerrar sesión
├── database.sql       → Script de creación de BD
├── css/
│   └── style.css      → Estilos
├── js/
│   └── app.js         → JavaScript del cliente
└── php/
    ├── config.php     → Conexión a la base de datos
    ├── auth.php       → Funciones de autenticación
    └── fichajes.php   → API AJAX para fichajes
```

---

## Instalación paso a paso

### 1. Base de datos

Accede a tu gestor MySQL (phpMyAdmin, terminal, etc.) y ejecuta:

```bash
mysql -u root -p < database.sql
```

O pega el contenido de `database.sql` directamente en phpMyAdmin.

Esto creará:
- La base de datos `fichajes_db`
- Las tablas `usuarios` y `fichajes`
- Un usuario admin y uno empleado de demo

### 2. Configurar conexión

Edita `php/config.php` y ajusta tus credenciales:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Tu usuario MySQL
define('DB_PASS', '');           // Tu contraseña MySQL
define('DB_NAME', 'fichajes_db');
```

### 3. Desplegar archivos

Copia toda la carpeta `fichajes_app/` a tu servidor web:
- **XAMPP/WAMP**: `htdocs/fichajes_app/`
- **Servidor Linux**: `/var/www/html/fichajes_app/`

### 4. Acceder

Abre en el navegador:
```
http://localhost/fichajes_app/
```

---

## Usuarios de demo

| Rol      | Email                  | Contraseña   |
|----------|------------------------|--------------|
| Admin    | admin@empresa.com      | admin123     |
| Empleado | juan@empresa.com       | empleado123  |

---

## Funcionalidades

- **Login / Registro** con contraseñas hasheadas (bcrypt)
- **Fichar entrada y salida** con fecha y hora real
- **Detección de retraso** automática vs horario asignado
- **Historial** de los últimos 30 fichajes del empleado
- **Panel admin**: ver todos los fichajes del día y editar horarios de cualquier usuario
- **Avisos**: pendiente de fichar salida, entrada tarde

---

## Requisitos del servidor

- PHP 7.4 o superior (con extensión PDO y pdo_mysql)
- MySQL 5.7+ o MariaDB 10.3+
- Servidor web (Apache, Nginx, XAMPP, WAMP...)
