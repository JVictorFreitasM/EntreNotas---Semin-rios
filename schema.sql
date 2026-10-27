CREATE DATABASE IF NOT EXISTS entrenotas
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE entrenotas;
-- Importar no banco já criado pelo painel InfinityFree (não usa CREATE DATABASE).
SET NAMES utf8mb4;
CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(50) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 private_code CHAR(4) NOT NULL UNIQUE,
 blocked TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE admins (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(50) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE system_state (
 id TINYINT UNSIGNED PRIMARY KEY,
 active_presentation_id BIGINT UNSIGNED NULL,
 installed TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;
INSERT INTO system_state (id) VALUES (1);
CREATE TABLE presentations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 presenter_id BIGINT UNSIGNED NOT NULL,
 real_name VARCHAR(120) NOT NULL,
 title VARCHAR(200) NOT NULL,
 status ENUM('registered','running','open','closed','ended') NOT NULL DEFAULT 'registered',
 INDEX idx_presenter_status (presenter_id,status),
 CONSTRAINT fk_presenter FOREIGN KEY (presenter_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Nenhuma nota, horário ou IP no controle de participação.
CREATE TABLE participation (
 presentation_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (presentation_id,user_id),
 FOREIGN KEY (presentation_id) REFERENCES presentations(id),
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
-- Sem ID sequencial, usuário, IP ou horário: resultados são exibidos ordenados por nota.
-- Escala interna canônica 0–10 para compatibilidade histórica.
-- Entrada e exibição são 0–5: salvar nota * 2 e ler nota / 2.
CREATE TABLE grades (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 presentation_id BIGINT UNSIGNED NOT NULL,
 grade DECIMAL(3,1) NOT NULL,
 INDEX idx_grades_presentation (presentation_id,grade),
 FOREIGN KEY (presentation_id) REFERENCES presentations(id),
 CONSTRAINT chk_grade CHECK (grade >= 0 AND grade <= 10)
) ENGINE=InnoDB;
-- Chaves HMAC; não armazena login ou IP em texto puro.
CREATE TABLE rate_limits (
 bucket CHAR(64) CHARACTER SET ascii PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 expires_at BIGINT UNSIGNED NOT NULL,
 INDEX idx_rate_expiry (expires_at)
) ENGINE=InnoDB;
