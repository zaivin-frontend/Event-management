<?php
require_once '../database/config.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Admin System Check</h2>";

try {
    $conn = getConnection();
    
    // Check database connection
    echo "<h3>Database Connection:</h3>";
    if ($conn->ping()) {
        echo "<p style='color: green;'>✓ Database connection successful</p>";
    } else {
        echo "<p style='color: red;'>✗ Database connection failed</p>";
    }
    
    // Check required tables
    echo "<h3>Required Tables:</h3>";
    $required_tables = ['admins', 'admin_tokens', 'admin_logs', 'password_resets'];
    $all_tables_exist = true;
    
    foreach ($required_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
            $all_tables_exist = false;
        }
    }
    
    // Check admin account
    echo "<h3>Admin Account:</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM admins");
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        echo "<p style='color: green;'>✓ Admin account exists</p>";
        
        // Get admin details
        $result = $conn->query("SELECT id, email, name, status, last_login FROM admins");
        echo "<h4>Admin Details:</h4>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Status</th><th>Last Login</th></tr>";
        
        while ($admin = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($admin['id']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['name']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['status']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['last_login'] ?? 'Never') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>✗ No admin account found</p>";
    }
    
    // Provide next steps
    echo "<h3>Next Steps:</h3>";
    if (!$all_tables_exist || $row['count'] === 0) {
        echo "<p style='color: orange;'>⚠️ Please run the setup script first:</p>";
        echo "<a href='setup_admin.php' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Setup</a>";
    } else {
        echo "<p style='color: green;'>✓ System appears to be properly configured</p>";
        echo "<p>Try logging in with these credentials:</p>";
        echo "<ul>";
        echo "<li>Email: admin@cdm.edu.ph</li>";
        echo "<li>Password: Admin@123</li>";
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?> 