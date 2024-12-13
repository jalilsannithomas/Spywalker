-- Modify users table to include admin role
ALTER TABLE users MODIFY COLUMN role ENUM('athlete', 'coach', 'fan', 'admin') NOT NULL;

-- Create admin user (password will be 'admin123')
INSERT INTO users (username, email, password, role) 
VALUES ('admin', 'admin@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
