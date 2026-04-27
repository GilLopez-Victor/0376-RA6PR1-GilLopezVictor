-- ============================================
-- ADMIN PANEL MIGRATION
-- Executar sobre la BD existent: fichajes_db
-- ============================================
 
USE fichajes_db;
 
-- Afegir camps que falten a projects (si no existeixen)
ALTER TABLE projects
    ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL,
    ADD FOREIGN KEY IF NOT EXISTS fk_projects_creator (created_by) REFERENCES usuarios(id) ON DELETE SET NULL;