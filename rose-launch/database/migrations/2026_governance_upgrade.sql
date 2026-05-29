-- PickMzansi governance alignment refactor migration
-- Apply on top of the upgraded MVP so the database matches the finalized governance model.
-- If your server does not support IF NOT EXISTS on ADD COLUMN, apply the statements manually once.

ALTER TABLE listings
    ADD COLUMN IF NOT EXISTS moderation_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS listing_status ENUM('active','grace_period','expired','archived','suspended','deleted') DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS verification_status ENUM('unverified','verified','reverification_required','rejected') DEFAULT 'unverified',
    ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS last_confirmed_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS last_reviewed_by INT NULL,
    ADD COLUMN IF NOT EXISTS moderation_reviewed_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS soft_deleted_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS listing_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    is_cover TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing_images_listing (listing_id),
    INDEX idx_listing_images_cover (listing_id, is_cover),
    CONSTRAINT fk_listing_images_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS listing_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    old_value LONGTEXT NULL,
    new_value LONGTEXT NULL,
    modified_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing_revisions_listing (listing_id),
    INDEX idx_listing_revisions_modified_by (modified_by),
    CONSTRAINT fk_listing_revisions_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action_type VARCHAR(120) NOT NULL,
    entity_type VARCHAR(120) NOT NULL,
    entity_id INT NULL,
    old_value LONGTEXT NULL,
    new_value LONGTEXT NULL,
    note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_logs_admin (admin_id),
    INDEX idx_admin_logs_action (action_type),
    INDEX idx_admin_logs_entity (entity_type, entity_id),
    CONSTRAINT fk_admin_logs_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    reporter_user_id INT NULL,
    reporter_phone VARCHAR(50) NULL,
    reporter_ip VARCHAR(45) NULL,
    report_type ENUM('fake_listing','unavailable_room','suspicious_behavior','wrong_details') NOT NULL,
    description TEXT NOT NULL,
    report_status ENUM('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    review_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reports_listing (listing_id),
    INDEX idx_reports_status (report_status),
    INDEX idx_reports_type (report_type),
    INDEX idx_reports_phone (reporter_phone),
    INDEX idx_reports_ip (reporter_ip),
    CONSTRAINT fk_reports_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS listing_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    metric_type ENUM('view','whatsapp_click','phone_click','renewal','report') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing_analytics_listing (listing_id),
    INDEX idx_listing_analytics_metric (metric_type),
    CONSTRAINT fk_listing_analytics_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

UPDATE listings
SET moderation_status = CASE
        WHEN moderation_status IS NULL OR moderation_status = '' THEN 'pending'
        ELSE moderation_status
    END,
    listing_status = CASE
        WHEN listing_status IS NULL OR listing_status = '' THEN 'active'
        ELSE listing_status
    END,
    verification_status = CASE
        WHEN verification_status IS NULL OR verification_status = '' THEN 'unverified'
        ELSE verification_status
    END;

UPDATE listings
SET listing_status = 'expired'
WHERE moderation_status = 'approved'
  AND expires_at IS NOT NULL
  AND expires_at < NOW();
