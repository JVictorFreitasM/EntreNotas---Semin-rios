-- Importar somente esta tabela adicional no banco existente, se necessário.
CREATE TABLE IF NOT EXISTS teacher_grades (
 presentation_id BIGINT UNSIGNED PRIMARY KEY,
 grade DECIMAL(2,1) NOT NULL,
 admin_id INT UNSIGNED NOT NULL,
 FOREIGN KEY (presentation_id) REFERENCES presentations(id),
 FOREIGN KEY (admin_id) REFERENCES admins(id),
 CONSTRAINT chk_teacher_grade CHECK (grade >= 0 AND grade <= 5)
) ENGINE=InnoDB;
