-- Add payment-related columns to events table
ALTER TABLE events
ADD COLUMN payment_required BOOLEAN DEFAULT FALSE,
ADD COLUMN payment_amount DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN payment_methods VARCHAR(255) DEFAULT NULL;

-- Create event_payments table
CREATE TABLE IF NOT EXISTS event_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    reference_number VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
); 