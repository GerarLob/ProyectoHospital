-- Crear base de datos (ejecutar en phpMyAdmin de XAMPP)
CREATE DATABASE IF NOT EXISTS hospital_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE hospital_db;

-- Usuarios del sistema (web master y permisos)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('web_master','admin','operador','visor') NOT NULL DEFAULT 'operador',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Empleados (personal del hospital)
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(20) NOT NULL UNIQUE, -- código QR/único del carné
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    dpi CHAR(13) NOT NULL UNIQUE,
    service VARCHAR(120) NOT NULL, -- área/servicio
    region VARCHAR(80) NOT NULL,
    has_vehicle TINYINT(1) NOT NULL DEFAULT 0,
    vehicles_count INT NOT NULL DEFAULT 0,
    plate_number VARCHAR(20) NULL,
    photo_path VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vehículos (cuando sea necesario)
CREATE TABLE IF NOT EXISTS employee_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    brand VARCHAR(60) NULL,
    model VARCHAR(60) NULL,
    plate VARCHAR(20) NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Bitácora de accesos y actividades
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(120) NOT NULL,
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Visitantes y carnés temporales (01-100 por tipo)
CREATE TABLE IF NOT EXISTS visitor_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    badge_number INT NOT NULL, -- 1..100
    badge_type ENUM('cuidador','tramitador','visitante') NOT NULL,
    color VARCHAR(20) NOT NULL, -- por tipo
    status ENUM('disponible','ocupado','mantenimiento') NOT NULL DEFAULT 'disponible',
    UNIQUE KEY unique_badge (badge_type, badge_number)
);

CREATE TABLE IF NOT EXISTS visitor_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    badge_id INT NOT NULL,
    dpi VARCHAR(20) NULL,
    first_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NULL,
    person_category ENUM('Cuidador','Tramitador','Visitante') NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    FOREIGN KEY (badge_id) REFERENCES visitor_badges(id)
);

-- Usuario inicial (web master)
INSERT INTO users (username, password_hash, role, active)
VALUES ('webmaster', '$2y$10$1oBaeJ6yC7XQmP0mGk4v1u6m9kXzEw5lq1kWkTzPCK1o7Yt8f8Xcq', 'web_master', 1)
ON DUPLICATE KEY UPDATE username = username;
-- La contraseña por defecto es: W3bM@ster2025 (hash Bcrypt ya incluido)


