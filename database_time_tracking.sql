-- ============================================
-- TIME TRACKING MIGRATION
-- Ejecutar sobre la BD existente: fichajes_db
-- ============================================

USE fichajes_db;

-- Proyectos
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    contracted_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Entradas de tiempo por proyecto
CREATE TABLE IF NOT EXISTS time_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    project_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME DEFAULT NULL,
    FOREIGN KEY (employee_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id)  REFERENCES projects(id)  ON DELETE CASCADE
);

-- Proyectos de demo
INSERT INTO projects (nombre, contracted_hours) VALUES
    ('Proyecto Alpha', 100),
    ('Proyecto Beta',  200),
    ('Soporte interno', 50)
ON DUPLICATE KEY UPDATE id=id;
