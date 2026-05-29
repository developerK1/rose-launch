-- PickMzansi governance hardening migration
-- Apply after the earlier governance upgrade migration.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email VARCHAR(191) NULL,
    ADD COLUMN IF NOT EXISTS trust_score INT NOT NULL DEFAULT 50,
    ADD COLUMN IF NOT EXISTS accepted_terms_version VARCHAR(32) NULL,
    ADD COLUMN IF NOT EXISTS accepted_terms_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(191) NULL,
    ADD COLUMN IF NOT EXISTS password_reset_expires_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS account_archived_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS identity_review_status ENUM('pending_verification','verified','trust_review_required','rejected') DEFAULT 'pending_verification',
    ADD COLUMN IF NOT EXISTS identity_reviewed_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS last_activity_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS verified_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS status ENUM('active','inactive','suspended','archived') DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS account_state ENUM('pending_verification','verified','identity_review_required','suspended','archived') DEFAULT 'pending_verification',
    ADD COLUMN IF NOT EXISTS whatsapp_verified TINYINT(1) DEFAULT 0;

ALTER TABLE listings
    ADD COLUMN IF NOT EXISTS property_type ENUM('backroom','student_room','cottage','house_room','shared_room','bachelor','guest_house','other') DEFAULT 'other',
    ADD COLUMN IF NOT EXISTS trust_score INT NOT NULL DEFAULT 50,
    ADD COLUMN IF NOT EXISTS public_note VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL;

ALTER TABLE listing_images
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS mime_type VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS original_name VARCHAR(255) NULL;

ALTER TABLE reports
    ADD COLUMN IF NOT EXISTS severity ENUM('low','medium','high','critical') DEFAULT 'medium',
    ADD COLUMN IF NOT EXISTS incident_case_id INT NULL;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(191) NOT NULL,
    message TEXT NOT NULL,
    related_entity_type VARCHAR(100) NULL,
    related_entity_id INT NULL,
    priority TINYINT NOT NULL DEFAULT 1,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    INDEX idx_notifications_user (user_id, is_read),
    INDEX idx_notifications_entity (related_entity_type, related_entity_id),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    listing_id INT NULL,
    category VARCHAR(80) NOT NULL,
    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
    subject VARCHAR(191) NOT NULL,
    status ENUM('open','under_review','awaiting_landlord','awaiting_admin','escalated','resolved','closed') NOT NULL DEFAULT 'open',
    assigned_to INT NULL,
    resolution_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    INDEX idx_support_tickets_user (user_id),
    INDEX idx_support_tickets_status (status),
    INDEX idx_support_tickets_category (category),
    CONSTRAINT fk_support_tickets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS support_ticket_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    sender_role VARCHAR(40) NOT NULL,
    sender_id INT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket_messages_ticket (ticket_id),
    CONSTRAINT fk_support_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS location_aliases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    province_id INT NULL,
    town_id INT NULL,
    alias_type ENUM('province','town') NOT NULL,
    alias_name VARCHAR(191) NOT NULL,
    normalized_alias VARCHAR(191) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_location_alias (normalized_alias, alias_type),
    INDEX idx_location_alias_town (town_id),
    INDEX idx_location_alias_province (province_id)
);

CREATE TABLE IF NOT EXISTS incident_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NULL,
    listing_id INT NULL,
    landlord_id INT NULL,
    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    status ENUM('open','under_review','monitor','resolved','closed') NOT NULL DEFAULT 'open',
    opened_by INT NULL,
    resolved_by INT NULL,
    note TEXT NULL,
    resolution_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    INDEX idx_incident_cases_landlord (landlord_id),
    INDEX idx_incident_cases_listing (listing_id),
    INDEX idx_incident_cases_status (status)
);

CREATE TABLE IF NOT EXISTS incident_evidence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    evidence_type VARCHAR(80) NOT NULL,
    evidence_payload LONGTEXT NOT NULL,
    added_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_incident_evidence_case (case_id)
);

CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    outcome VARCHAR(40) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_history_user (user_id)
);
