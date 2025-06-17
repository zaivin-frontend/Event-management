// ... existing code ...

-- Create settings table
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'CDM Event Management'),
('site_description', 'Event Management System for CDM'),
('admin_email', 'admin@example.com'),
('max_events_per_user', '5'),
('max_participants_per_event', '100'),
('allow_event_registration', '1'),
('maintenance_mode', '0'),
('registration_approval_required', '1'),
('email_notifications', '1'),
('default_timezone', 'Asia/Manila');

// ... existing code ...