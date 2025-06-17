<?php
require_once __DIR__ . '/../database/config.php';

/**
 * Get a setting value by key
 * 
 * @param string $key The setting key
 * @param mixed $default The default value if setting is not found
 * @return mixed The setting value or default value
 */
function get_setting($key, $default = null) {
    global $conn;
    
    $query = "SELECT value FROM settings WHERE setting_key = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['value'];
    }
    
    return $default;
}

/**
 * Update a setting value
 * 
 * @param string $key The setting key
 * @param mixed $value The new value
 * @return bool True if successful, false otherwise
 */
function update_setting($key, $value) {
    global $conn;
    
    $query = "UPDATE settings SET value = ? WHERE setting_key = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $value, $key);
    
    return $stmt->execute();
}

/**
 * Get all settings
 * 
 * @return array Associative array of settings
 */
function get_all_settings() {
    global $conn;
    
    $settings = [];
    $query = "SELECT setting_key, value FROM settings";
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['value'];
    }
    
    return $settings;
}

/**
 * Check if a boolean setting is enabled
 * 
 * @param string $key The setting key
 * @return bool True if enabled, false otherwise
 */
function is_setting_enabled($key) {
    return get_setting($key, '0') === '1';
}

/**
 * Get settings by category
 * 
 * @param string $category The category prefix (e.g., 'email_', 'security_')
 * @return array Associative array of settings in the category
 */
function get_settings_by_category($category) {
    global $conn;
    
    $settings = [];
    $query = "SELECT setting_key, value FROM settings WHERE setting_key LIKE ?";
    $stmt = $conn->prepare($query);
    $category = $category . '%';
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['value'];
    }
    
    return $settings;
}

/**
 * Validate and sanitize a setting value
 * 
 * @param string $key The setting key
 * @param mixed $value The value to validate
 * @return mixed The sanitized value or false if invalid
 */
function validate_setting($key, $value) {
    switch ($key) {
        case 'site_name':
            return trim(strip_tags($value));
            
        case 'site_description':
            return trim(strip_tags($value));
            
        case 'contact_email':
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : false;
            
        case 'email_notifications':
        case 'registration_notifications':
        case 'event_reminders':
        case 'require_special_chars':
            return in_array($value, ['0', '1']) ? $value : false;
            
        case 'password_min_length':
            $value = (int)$value;
            return ($value >= 6 && $value <= 32) ? $value : false;
            
        case 'session_timeout':
            $value = (int)$value;
            return ($value >= 5 && $value <= 1440) ? $value : false;
            
        default:
            return false;
    }
} 