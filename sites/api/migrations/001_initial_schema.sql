-- ============================================
-- WebIArtisan Platform — Initial Schema
-- Multi-tenant architecture
-- ============================================

-- Tenants (each client = 1 tenant)
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    subdomain VARCHAR(100),
    custom_domain VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users with SSO
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'viewer') DEFAULT 'manager',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Branding (1 per tenant)
CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNIQUE NOT NULL,
    company_name VARCHAR(100),
    slogan VARCHAR(255),
    logo_svg TEXT,
    colors JSON,
    style JSON,
    contact JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generated sites
CREATE TABLE IF NOT EXISTS sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    token VARCHAR(32) UNIQUE NOT NULL,
    config JSON NOT NULL,
    html LONGTEXT NOT NULL,
    is_published BOOLEAN DEFAULT FALSE,
    public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_tenant (tenant_id),
    INDEX idx_published (tenant_id, is_published),
    INDEX idx_public (public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clients
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    societe VARCHAR(100),
    nom VARCHAR(100),
    email VARCHAR(255),
    telephone VARCHAR(20),
    adresse TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Devis
CREATE TABLE IF NOT EXISTS devis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    client_id INT,
    numero VARCHAR(50) NOT NULL,
    status ENUM('draft','sent','accepted','refused','invoiced') DEFAULT 'draft',
    lignes JSON,
    total_ht DECIMAL(10,2) DEFAULT 0,
    tva_rate DECIMAL(4,2) DEFAULT 0,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Factures
CREATE TABLE IF NOT EXISTS factures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    client_id INT,
    devis_id INT,
    numero VARCHAR(50) NOT NULL,
    status ENUM('draft','sent','paid','overdue') DEFAULT 'draft',
    lignes JSON,
    total_ht DECIMAL(10,2) DEFAULT 0,
    tva_rate DECIMAL(4,2) DEFAULT 0,
    sent_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (devis_id) REFERENCES devis(id) ON DELETE SET NULL,
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chantiers (for mobile app)
CREATE TABLE IF NOT EXISTS chantiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    client_id INT,
    nom VARCHAR(100),
    adresse TEXT,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    status ENUM('planned','active','paused','completed') DEFAULT 'planned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chantier medias (photos, audio notes)
CREATE TABLE IF NOT EXISTS chantier_medias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chantier_id INT NOT NULL,
    type ENUM('photo','audio') NOT NULL,
    file_path VARCHAR(500),
    note TEXT,
    geo_lat DECIMAL(10,7),
    geo_lng DECIMAL(10,7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chantier_id) REFERENCES chantiers(id) ON DELETE CASCADE,
    INDEX idx_chantier (chantier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
