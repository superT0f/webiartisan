-- ============================================
-- WebIArtisan Platform — Contact Messages Management
-- Creates messages table for contact form submissions
-- ============================================

-- Step 1: Create messages table
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL,
    company VARCHAR(255) NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    postal_code VARCHAR(10) NULL,
    meeting_type ENUM('Pas de préférence', 'Visioconférence', 'Présentiel Seine-et-Marne', 'Téléphone') DEFAULT 'Pas de préférence',
    availability ENUM('Cette semaine', 'Semaine prochaine', 'Dans 2-3 semaines', 'Sans urgence') DEFAULT 'Sans urgence',
    employees VARCHAR(20) NULL,
    ip_address VARCHAR(45) NULL,
    country_code VARCHAR(2) NULL,
    user_agent TEXT NULL,
    status ENUM('new', 'read', 'in_progress', 'completed', 'archived') DEFAULT 'new',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    assigned_to INT NULL,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    completed_at DATETIME NULL,
    
    -- Indexes for performance
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created (created_at),
    INDEX idx_email (email),
    INDEX idx_assigned (assigned_to),
    
    -- Foreign key to users table (for assigned_to)
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Create message_notes table for internal communication
CREATE TABLE IF NOT EXISTS message_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    internal BOOLEAN DEFAULT TRUE,  -- true = internal note, false = visible to customer
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_message (message_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Create message_attachments table
CREATE TABLE IF NOT EXISTS message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 4: Create message_tags table for categorization
CREATE TABLE IF NOT EXISTS message_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#6c757d',
    description TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 5: Create message_tag_relations table
CREATE TABLE IF NOT EXISTS message_tag_relations (
    message_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (message_id, tag_id),
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES message_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 6: Insert default tags
INSERT IGNORE INTO message_tags (name, color, description) VALUES
('Lead', '#28a745', 'Nouveau prospect potentiel'),
('Support', '#17a2b8', 'Demande de support technique'),
('Commercial', '#ffc107', 'Question commerciale'),
('Demo', '#6f42c1', 'Demande de démonstration'),
('Urgent', '#dc3545', 'Message urgent'),
('Spam', '#6c757d', 'Spam ou non pertinent');

-- Step 7: Update users table to add message permissions
SET @dbname = DATABASE();
SET @tablename = 'users';

-- Add can_manage_messages column if not exists
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'can_manage_messages'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN can_manage_messages BOOLEAN DEFAULT FALSE AFTER role',
    'SELECT "Column can_manage_messages already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
