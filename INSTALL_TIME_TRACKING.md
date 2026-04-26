# INSTALL_TIME_TRACKING.md
# Guía de instalación — Extensión Time Tracking

Esta guía cubre únicamente la extensión de control de tiempo por proyecto
que se añade sobre la aplicación de fichajes existente.

---

## 1. Requisitos del sistema

- PHP 7.4 o superior (extensiones: `pdo`, `pdo_mysql`)
- MySQL 5.7+ o MariaDB 10.3+
- Servidor web con soporte PHP (Apache/Nginx/XAMPP/WAMP)
- La app base (`fichajes_app`) ya instalada y funcionando

---

## 2. Dependencias

No se introducen dependencias nuevas.
Todo el código usa PHP puro + JS vanilla, igual que la app base.

---

## 3. Variables de entorno requeridas

No hay variables de entorno nuevas.
La conexión reutiliza `php/config.php` existente:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // tu usuario MySQL
define('DB_PASS', '');           // tu contraseña MySQL
define('DB_NAME', 'fichajes_db');
```

---

## 4. Configuración de la base de datos

El fichero `database_time_tracking.sql` crea dos tablas nuevas y datos de demo.
**No modifica ninguna tabla existente.**

Tablas creadas:
- `projects` (id, nombre, contracted_hours, activo, created_at)
- `time_entries` (id, employee_id, project_id, start_time, end_time)

---

## 5. Comandos de migración

Ejecutar **una sola vez** sobre la base de datos existente:

```bash
# Opción A — desde terminal
mysql -u root -p fichajes_db < database_time_tracking.sql

# Opción B — desde phpMyAdmin
# Importar el fichero database_time_tracking.sql en la BD fichajes_db
```

Verificar que las tablas se han creado:

```sql
USE fichajes_db;
SHOW TABLES;
-- Deben aparecer: usuarios, fichajes, projects, time_entries

SELECT * FROM projects;
-- Deben aparecer 3 proyectos de demo
```

---

## 6. Archivos nuevos a desplegar

Copiar estos archivos al servidor (junto con los existentes):

```
fichajes_app/
├── database_time_tracking.sql   ← ejecutar en BD
├── php/
│   ├── time_tracking.php        ← endpoint AJAX empleados
│   └── api_reports.php          ← API JSON para manager
└── INSTALL_TIME_TRACKING.md     ← este fichero
```

Archivos modificados (ya actualizados en el proyecto):

```
fichajes_app/
├── dashboard.php    ← nuevas pestañas Proyectos + Manager
└── js/app.js        ← nuevas funciones ttAction(), loadManagerData()
```

---

## 7. Cómo ejecutar el backend

No hay proceso separado. El backend es PHP síncrono.

```bash
# XAMPP — copiar carpeta a htdocs y acceder a:
http://localhost/fichajes_app/

# PHP built-in server (desarrollo)
cd fichajes_app
php -S localhost:8000
# Acceder a: http://localhost:8000/
```

---

## 8. Cómo probar el sistema de time tracking

### Como empleado

1. Acceder con `juan@empresa.com` / `empleado123`
2. Ir a la pestaña **Proyectos**
3. Seleccionar un proyecto en el desplegable
4. Pulsar **▶ Iniciar trabajo** → se crea una `time_entry` activa
5. Cambiar proyecto con **🔄 Cambiar proyecto** → cierra la entrada anterior, abre una nueva
6. Pulsar **■ Parar trabajo** → cierra la entrada activa

### Como admin/manager

1. Acceder con `admin@empresa.com` / `admin123`
2. Ir a la pestaña **Manager**
3. Se cargan automáticamente:
   - Empleados trabajando ahora mismo
   - Lista roja de incumplimientos del día
   - Barras de progreso por proyecto
4. Pulsar **↻ Actualizar** para refrescar los datos

---

## 9. Llamadas API de ejemplo

Todos los endpoints requieren sesión PHP activa (cookie de sesión).

### Sesiones activas

```
GET /php/api_reports.php?type=active
```

Respuesta:
```json
{
  "active_sessions": [
    {
      "id": 3,
      "employee": "Juan García",
      "project": "Proyecto Alpha",
      "start_time": "2024-01-15 09:32:00",
      "hours_running": 1.45
    }
  ]
}
```

### Lista roja

```
GET /php/api_reports.php?type=red_list
```

Respuesta:
```json
{
  "red_list": [
    {
      "employee": "Juan García",
      "issue": "late_arrival",
      "clock_in": "09:15:00",
      "expected_in": "08:30:00"
    },
    {
      "employee": "Ana López",
      "issue": "insufficient_hours",
      "hours_today": 5.0,
      "expected": 8.0
    }
  ]
}
```

Posibles valores de `issue`:
- `late_arrival` — llegó tarde
- `early_exit` — salió antes de hora
- `insufficient_hours` — horas trabajadas < horas esperadas (al cerrar jornada)
- `no_clock_in` — no ha fichado entrada y ya pasó su hora de entrada

### Resumen de proyectos

```
GET /php/api_reports.php?type=projects
```

Respuesta:
```json
{
  "projects": [
    {
      "id": 1,
      "project": "Proyecto Alpha",
      "contracted_hours": 100,
      "used_hours": 23.5,
      "remaining_hours": 76.5,
      "percent_used": 23.5
    }
  ]
}
```

### Iniciar trabajo (AJAX POST)

```
POST /php/time_tracking.php
action=start&project_id=1
```

Respuesta:
```json
{ "success": true, "mensaje": "▶ Trabajando en: Proyecto Alpha", "since": "2024-01-15 10:00:00" }
```

### Cambiar proyecto

```
POST /php/time_tracking.php
action=switch&project_id=2
```

### Parar trabajo

```
POST /php/time_tracking.php
action=stop
```

### Estado actual del empleado

```
POST /php/time_tracking.php
action=status
```

Respuesta:
```json
{
  "success": true,
  "active": {
    "id": 5,
    "employee_id": 2,
    "project_id": 1,
    "start_time": "2024-01-15 10:00:00",
    "end_time": null,
    "project_name": "Proyecto Alpha"
  },
  "hours_today": 1.25
}
```

---

## 10. Resolución de problemas

### "Error de conexión a la base de datos"
→ Revisar credenciales en `php/config.php`

### Las tablas `projects` o `time_entries` no existen
→ Ejecutar la migración: `mysql -u root -p fichajes_db < database_time_tracking.sql`

### El desplegable de proyectos aparece vacío
→ Verificar que hay registros en la tabla `projects` con `activo=1`:
```sql
SELECT * FROM projects WHERE activo=1;
```

### La pestaña Manager no carga datos
→ Asegurarse de estar logueado como `admin`. El endpoint `api_reports.php` devuelve 403 para no-admins.

### "Ya tienes una sesión activa" al pulsar Iniciar
→ El empleado tiene una `time_entry` sin `end_time`. Usar **Parar trabajo** primero, o consultar:
```sql
SELECT * FROM time_entries WHERE employee_id = ? AND end_time IS NULL;
```

### Las horas de proyectos no se actualizan
→ Las horas se calculan en tiempo real con `NOW()` para entradas activas.
Pulsar **↻ Actualizar** en la pestaña Manager para refrescar.

---

## Estructura de tablas para referencia

```sql
-- Proyectos
CREATE TABLE projects (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(150) NOT NULL,
    contracted_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
    activo           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Entradas de tiempo
CREATE TABLE time_entries (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT      NOT NULL,   -- FK → usuarios.id
    project_id  INT      NOT NULL,   -- FK → projects.id
    start_time  DATETIME NOT NULL,
    end_time    DATETIME DEFAULT NULL  -- NULL = sesión activa
);
```
