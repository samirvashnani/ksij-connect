-- Medical-only fundraising and private supporting documents.
-- Run this migration once on the existing shared database before using the new pages.
-- Existing funds remain unclassified; no existing data is deleted or relabelled.
ALTER TABLE funds
    ADD COLUMN purpose ENUM('medical') NULL DEFAULT NULL AFTER reason,
    ADD COLUMN medical_details TEXT NULL AFTER purpose;

CREATE TABLE fund_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fund_id INT NOT NULL,
    document_type ENUM('medical_report','treatment_plan','cost_estimate','other') NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fund_documents_fund_type (fund_id, document_type),
    CONSTRAINT fk_fund_documents_fund FOREIGN KEY (fund_id) REFERENCES funds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
