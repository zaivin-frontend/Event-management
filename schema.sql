-- Create database if not exists
CREATE DATABASE IF NOT EXISTS event_management;
USE event_management;

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json', 'array') DEFAULT 'text',
    setting_group VARCHAR(50) DEFAULT 'general',
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key),
    INDEX idx_setting_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, is_public) VALUES
('site_name', 'Event Management System', 'text', 'general', TRUE),
('site_description', 'College of Divine Mercy Event Management System', 'text', 'general', TRUE),
('admin_email', 'admin@cdm.edu.ph', 'text', 'email', TRUE),
('max_registrations_per_event', '100', 'number', 'events', FALSE),
('allow_guest_registrations', 'false', 'boolean', 'events', FALSE),
('maintenance_mode', 'false', 'boolean', 'system', FALSE),
('registration_approval_required', 'true', 'boolean', 'events', FALSE),
('default_event_status', 'draft', 'text', 'events', FALSE),
('max_events_per_page', '10', 'number', 'display', TRUE),
('date_format', 'Y-m-d H:i:s', 'text', 'display', TRUE),
('timezone', 'Asia/Manila', 'text', 'system', TRUE),
('logo_path', 'assets/images/logo.png', 'text', 'general', TRUE),
('favicon_path', 'assets/images/favicon.ico', 'text', 'general', TRUE),
('theme_color', '#007bff', 'text', 'appearance', TRUE),
('footer_text', '© 2024 College of Divine Mercy. All rights reserved.', 'text', 'general', TRUE),
('social_links', '{"facebook":"","twitter":"","instagram":""}', 'json', 'social', TRUE),
('email_settings', '{"smtp_host":"","smtp_port":"","smtp_user":"","smtp_pass":"","smtp_secure":"tls"}', 'json', 'email', FALSE),
('notification_settings', '{"email_notifications":true,"registration_notifications":true,"event_reminders":true}', 'json', 'notifications', FALSE);

-- Admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL DEFAULT NULL,
    login_attempts INT UNSIGNED DEFAULT 0,
    last_attempt TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_last_login (last_login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admins
INSERT INTO admins (email, password, name, status) VALUES
('admin@cdm.edu.ph', 'password123', 'Admin User', 'active'); 