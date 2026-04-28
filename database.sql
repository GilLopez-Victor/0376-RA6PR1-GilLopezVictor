-- ============================================
-- FICHAJES APP - Script de base de datos
-- Ejecutar en MySQL/MariaDB antes de usar la app
-- ============================================

CREATE DATABASE IF NOT EXISTS fichajes_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fichajes_db;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'empleado') NOT NULL DEFAULT 'empleado',
    hora_entrada TIME NOT NULL DEFAULT '09:00:00',
    hora_salida TIME NOT NULL DEFAULT '17:00:00',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de fichajes
CREATE TABLE IF NOT EXISTS fichajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_entrada_real TIME DEFAULT NULL,
    hora_salida_real TIME DEFAULT NULL,
    tarde TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Usuario admin de ejemplo (contraseña: admin123)
INSERT INTO usuarios (nombre, email, password, rol, hora_entrada, hora_salida)
VALUES (
    'Administrador',
    'admin@empresa.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    '09:00:00',
    '17:00:00'
) ON DUPLICATE KEY UPDATE id=id;

-- Empleado de ejemplo (contraseña: empleado123)
INSERT INTO usuarios (nombre, email, password, rol, hora_entrada, hora_salida)
VALUES (
    'Juan García',
    'juan@empresa.com',
    '$2y$10$TKh8H1.PdfkbmaoP.OHsBOBRpT2/E0.s7r09Xk6GXyQA6Jp3Fzl3a',
    'empleado',
    '08:30:00',
    '16:30:00'
) ON DUPLICATE KEY UPDATE id=id;

-- Afegir camps que falten a projects (si no existeixen)
ALTER TABLE projects
    ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL,
    ADD FOREIGN KEY IF NOT EXISTS fk_projects_creator (created_by) REFERENCES usuarios(id) ON DELETE SET NULL;