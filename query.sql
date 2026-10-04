-- Formal-request education and medical details. Run once on the shared database.
-- Existing requests and legacy document_path attachments are preserved.
ALTER TABLE requests
    ADD COLUMN education_grade VARCHAR(60) NULL,
    ADD COLUMN school_name VARCHAR(150) NULL,
    ADD COLUMN last_exam_marks DECIMAL(7,2) NULL,
    ADD COLUMN last_exam_total DECIMAL(7,2) NULL,
    ADD COLUMN medical_title VARCHAR(150) NULL,
    ADD COLUMN medical_details TEXT NULL,
    ADD COLUMN due_date DATE NULL,
    ADD COLUMN medical_confirmation TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS request_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    document_type ENUM('exam_result','medical_report','treatment_plan','cost_estimate','other') NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_request_documents_request_type (request_id, document_type),
    CONSTRAINT fk_request_documents_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Presentation-only membership payment audit. No actual money is collected.
CREATE TABLE IF NOT EXISTS membership_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_mode ENUM('demo') NOT NULL DEFAULT 'demo',
    reference VARCHAR(32) NOT NULL UNIQUE,
    submission_token CHAR(64) NOT NULL UNIQUE,
    paid_on DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_membership_payments_member (member_id, id),
    CONSTRAINT fk_membership_payments_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
