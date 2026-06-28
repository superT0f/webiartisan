-- Create status_logs table to track site availability
CREATE TABLE IF NOT EXISTS status_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id VARCHAR(50) NOT NULL,
    status ENUM('online', 'offline') NOT NULL,
    response_time INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (site_id),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
