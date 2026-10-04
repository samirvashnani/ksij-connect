-- ============================================================
-- KSIJ Platform — Full Database Schema + Dummy Data
-- ============================================================

CREATE DATABASE IF NOT EXISTS ksij_platform;
USE ksij_platform;

-- ============================================================
-- MEMBERS
-- ============================================================
CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    membership_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    area VARCHAR(100),
    membership_status ENUM('active','expired','pending') DEFAULT 'active',
    fees_due DECIMAL(10,2) DEFAULT 0,
    fees_last_paid_date DATE,
    renewal_date DATE,
    payment_link VARCHAR(255),
    wallet_balance DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO members (membership_id, full_name, email, phone, area, membership_status, fees_due, fees_last_paid_date, renewal_date, payment_link, wallet_balance) VALUES
('KSIJ001', 'Ali Raza',      'ali.raza.test@gmail.com',      '9876543210', 'Mumbai',  'active',  0.00,    '2026-03-15', '2027-03-15', 'https://pay.example.com/ksij001', 1000.00),
('KSIJ002', 'Fatima Zahra',  'fatima.zahra.test@gmail.com',  '9876543211', 'Mumbai',  'pending', 500.00,  '2025-02-10', '2026-02-10', 'https://pay.example.com/ksij002', 200.00),
('KSIJ003', 'Hasan Abidi',   'hasan.abidi.test@gmail.com',   '9876543212', 'Palghar', 'pending', 1200.00, '2024-12-01', '2025-12-01', 'https://pay.example.com/ksij003', 0.00),
('KSIJ004', 'Zainab Kazmi',  'zainab.kazmi.test@gmail.com',  '9876543213', 'Palghar', 'active',  0.00,    '2026-06-20', '2027-06-20', 'https://pay.example.com/ksij004', 500.00),
('KSIJ005', 'Mehdi Jafferi', 'mehdi.jafferi.test@gmail.com', '9876543214', 'Palghar', 'expired', 800.00,  '2024-05-05', '2025-05-05', 'https://pay.example.com/ksij005', 0.00);

-- ============================================================
-- OTP VERIFICATION
-- ============================================================
CREATE TABLE membership_payments (
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

CREATE TABLE otp_verification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    membership_id VARCHAR(20) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_used TINYINT(1) DEFAULT 0
);

-- ============================================================
-- EVENTS
-- ============================================================
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    event_date DATE,
    event_time TIME,
    venue VARCHAR(150),
    registration_required TINYINT(1) DEFAULT 0,
    registration_link VARCHAR(255)
);

INSERT INTO events (title, description, event_date, event_time, venue, registration_required, registration_link) VALUES
('Majlis-e-Aza — Muharram', 'Weekly Majlis commemorating the events of Karbala, open to all community members.', '2026-10-05', '19:00:00', 'Main Jamaat Hall, Mumbai', 0, NULL),
('Annual Community Iftar', 'Community-wide Iftar gathering with food, talks, and volunteer coordination.', '2026-11-12', '18:30:00', 'KSIJ Community Ground', 1, 'https://forms.example.com/iftar2026'),
('Youth Career Fair', 'Career guidance and networking event connecting students with mentors and employers.', '2026-10-18', '10:00:00', 'KSIJ Youth Center', 1, 'https://forms.example.com/careerfair2026');

-- ============================================================
-- MEMBERSHIP INFO
-- ============================================================
CREATE TABLE membership_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic VARCHAR(150) NOT NULL,
    details TEXT NOT NULL
);

INSERT INTO membership_info (topic, details) VALUES
('New Membership Process', 'Visit the office with Aadhar card copy, a passport-size photo, and a recommendation from an existing member. Reviewed within 2 weeks.'),
('Renewal Process', 'Due annually on registration date. Reminder sent 30 days before. Renew online via member portal or in person.'),
('Membership Fees Structure', 'Annual fee ₹1200 for individuals, ₹2000 for families. 50% concession for students and senior citizens.'),
('Required Documents for Membership', 'Aadhar card copy, one passport-size photo, proof of address, reference letter from an existing member.');

-- ============================================================
-- PROJECTS
-- ============================================================
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    status ENUM('upcoming','ongoing','completed') NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE
);

INSERT INTO projects (name, status, description, start_date, end_date) VALUES
('Community Health Camp', 'completed', 'Free medical checkup camp covering general health, eye checkups, and diabetes screening.', '2026-01-10', '2026-01-12'),
('Digital Literacy Program', 'ongoing', 'Free smartphone/internet classes for senior citizens and homemakers.', '2026-08-01', '2026-12-31'),
('New Community Hall Construction', 'upcoming', 'Construction of a new multi-purpose community hall.', '2027-01-01', '2027-12-31');

-- ============================================================
-- SCHOLARSHIPS
-- ============================================================
CREATE TABLE scholarships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    eligibility TEXT,
    amount VARCHAR(100),
    deadline DATE,
    how_to_apply TEXT
);

INSERT INTO scholarships (name, eligibility, amount, deadline, how_to_apply) VALUES
('Merit Scholarship for Engineering Students', 'Community members in a recognized engineering program, min 75% marks previous year.', 'Up to ₹25,000/year', '2026-11-30', 'Submit form with mark sheets and fee receipt to the Education Committee.'),
('Need-Based Education Assistance', 'Family income below ₹3,00,000/year, enrolled in recognized school/college.', 'Up to ₹15,000/year', '2026-12-15', 'Submit income proof, admission proof, and recommendation letter to the Welfare Office.');

-- ============================================================
-- WELFARE SCHEMES
-- ============================================================
CREATE TABLE welfare_schemes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scheme_name VARCHAR(150) NOT NULL,
    type ENUM('medical','ration','education','emergency','other') NOT NULL,
    eligibility TEXT,
    coverage TEXT,
    how_to_apply TEXT
);

INSERT INTO welfare_schemes (scheme_name, type, eligibility, coverage, how_to_apply) VALUES
('Medical Aid Fund', 'medical', 'Any registered member facing significant medical expenses, subject to committee review.', 'Up to ₹50,000 per case.', 'Submit medical bills, prescription, and application form to the Welfare Committee.'),
('Monthly Ration Support', 'ration', 'Families below poverty line registered with the welfare office.', 'Monthly ration kit for a family of 4-5.', 'Contact the Welfare Office with proof of income.');

-- ============================================================
-- CONTACTS / DEPARTMENTS
-- ============================================================
CREATE TABLE contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(100) NOT NULL,
    person_name VARCHAR(100),
    designation VARCHAR(100),
    phone VARCHAR(15),
    email VARCHAR(100),
    availability VARCHAR(100)
);

INSERT INTO contacts (department, person_name, designation, phone, email, availability) VALUES
('Membership', 'Syed Ahmed', 'Membership Secretary', '9820012345', 'membership@ksijmumbai.org', 'Mon–Fri, 10 AM–5 PM'),
('Scholarships', 'Mohammed Hussain', 'Education Committee Head', '9820012346', 'education@ksijmumbai.org', 'Mon–Sat, 11 AM–4 PM'),
('Medical Aid', 'Dr. Asghar Naqvi', 'Welfare Committee Head', '9820012347', 'welfare@ksijmumbai.org', 'Mon–Fri, 9 AM–1 PM'),
('Events', 'Sakina Rizvi', 'Events Coordinator', '9820012348', 'events@ksijmumbai.org', 'Mon–Sat, 10 AM–6 PM'),
('General Office', 'KSIJ Front Desk', 'Office Administration', '9820012300', 'office@ksijmumbai.org', 'Mon–Sat, 9 AM–6 PM');

-- ============================================================
-- GENERAL INFO
-- ============================================================
CREATE TABLE general_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100),
    title VARCHAR(150),
    content TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO general_info (category, title, content) VALUES
('Timings', 'Office Hours', 'The KSIJ Jamaat office is open Monday to Saturday, 9 AM to 6 PM.'),
('Timings', 'Friday Majlis Timing', 'Weekly Friday Majlis every Friday at 7:00 PM at the Main Jamaat Hall.'),
('Rules', 'Hall Booking Policy', 'Community hall bookings must be requested at least 15 days in advance.'),
('General', 'Jamaat Address', 'KSIJ Mumbai Jamaat Khana, [Sample Address], Mumbai, Maharashtra, India.');

-- ============================================================
-- NEWS / ANNOUNCEMENTS
-- ============================================================
CREATE TABLE news_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(100),
    is_pinned TINYINT(1) DEFAULT 0,
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO news_updates (title, content, category, is_pinned) VALUES
('Friday Majlis Venue Change This Week', 'Due to maintenance, this Friday''s Majlis will be at the KSIJ Youth Center instead of the Main Jamaat Hall. Same timing, 7:00 PM.', 'Event Change', 1),
('Scholarship Deadline Extended', 'Merit Scholarship deadline extended by two weeks due to high demand. New deadline: 14th December 2026.', 'Deadline', 1),
('New Online Payment Option for Membership Fees', 'Members can now pay renewal fees online via UPI through the member portal.', 'Announcement', 0),
('Office Closed for Public Holiday', 'KSIJ office closed 2nd October 2026 for a public holiday.', 'Announcement', 0);

-- ============================================================
-- STAFF USERS (volunteers, CC members, admins)
-- Password for ALL dummy accounts below: test123
-- is_guarantor_approved: only matters for role='volunteer' — admin
-- must explicitly approve a volunteer before members can select
-- them as a guarantor on a request.
-- ============================================================
CREATE TABLE staff_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('volunteer','cc_member','admin') NOT NULL,
    area VARCHAR(100),
    is_guarantor_approved TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    phone VARCHAR(15),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO staff_users (full_name, username, password_hash, role, area, is_guarantor_approved, is_active) VALUES
('Volunteer Mumbai', 'volunteer1', '$2b$12$MRdsnksntUNzPi2eDVD9MOwAEyjzN5DkgMlJPkYpVWQM4HEoa4ibe', 'volunteer', 'Mumbai', 1, 1),
('Volunteer Palghar', 'volunteer2', '$2b$12$MRdsnksntUNzPi2eDVD9MOwAEyjzN5DkgMlJPkYpVWQM4HEoa4ibe', 'volunteer', 'Palghar', 1, 1),
('Volunteer No-Guarantor', 'volunteer3', '$2b$12$MRdsnksntUNzPi2eDVD9MOwAEyjzN5DkgMlJPkYpVWQM4HEoa4ibe', 'volunteer', 'Mumbai', 0, 1),
('Volunteer Inactive', 'volunteer4', '$2b$12$MRdsnksntUNzPi2eDVD9MOwAEyjzN5DkgMlJPkYpVWQM4HEoa4ibe', 'volunteer', 'Mumbai', 1, 0),
('CC Member Mumbai', 'ccmember1', '$2b$12$MRdsnksntUNzPi2eDVD9MOwAEyjzN5DkgMlJPkYpVWQM4HEoa4ibe', 'cc_member', 'Mumbai', 0, 1),
('CC Member Palghar', 'ccmember2', '$2b$12$MRdsnksntUNzPi2eDVD9MOwAEyjzN5DkgMlJPkYpVWQM4HEoa4ibe', 'cc_member', 'Palghar', 0, 1),
('Admin User', 'admin1', '$2b$12$MRdsnksntUNzPi2eDVD9MOwAEyjzN5DkgMlJPkYpVWQM4HEoa4ibe', 'admin', NULL, 0, 1);

-- ============================================================
-- REQUESTS (Scholarship / Medical Aid / Loan)
-- Member selects TWO same-area guarantors (active CC members or approved volunteers).
-- BOTH must approve before the office can give final approval.
-- ============================================================
CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    type ENUM('scholarship','medical_aid','loan') NOT NULL,
    description TEXT NOT NULL,
    amount_requested DECIMAL(10,2),
    document_path VARCHAR(255),
    education_grade VARCHAR(60),
    school_name VARCHAR(150),
    last_exam_marks DECIMAL(7,2),
    last_exam_total DECIMAL(7,2),
    medical_title VARCHAR(150),
    medical_details TEXT,
    due_date DATE,
    medical_confirmation TINYINT(1) NOT NULL DEFAULT 0,
    guarantor1_id INT,
    guarantor1_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    guarantor1_notes TEXT,
    guarantor2_id INT,
    guarantor2_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    guarantor2_notes TEXT,
    office_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (guarantor1_id) REFERENCES staff_users(id),
    FOREIGN KEY (guarantor2_id) REFERENCES staff_users(id)
);

CREATE TABLE request_documents (
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

-- Dummy requests, each with both guarantor slots filled (volunteer1 + volunteer2)
INSERT INTO requests (member_id, type, description, amount_requested, guarantor1_id, guarantor2_id)
SELECT m.id, 'scholarship', 'Requesting support for final year engineering tuition fees.', 25000.00,
       (SELECT id FROM staff_users WHERE username = 'volunteer1'),
       (SELECT id FROM staff_users WHERE username = 'volunteer2')
FROM members m WHERE m.membership_id = 'KSIJ002';

INSERT INTO requests (member_id, type, description, amount_requested, guarantor1_id, guarantor2_id)
SELECT m.id, 'medical_aid', 'Need assistance for ongoing medical treatment costs.', 40000.00,
       (SELECT id FROM staff_users WHERE username = 'volunteer1'),
       (SELECT id FROM staff_users WHERE username = 'volunteer2')
FROM members m WHERE m.membership_id = 'KSIJ003';

-- ============================================================
-- HELP REQUESTS (general assistance — elderly help, urgent need, etc.)
-- Different from the above: visible to ALL members, ALL volunteers,
-- and admin. No guarantor chain — admin assigns a volunteer directly.
-- ============================================================
CREATE TABLE help_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NULL,                 -- NULL when a CC member or admin creates a direct task, not tied to a member's own request
    category ENUM('elderly_help','urgent_medical','other') NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open','assigned','resolved') DEFAULT 'open',
    assigned_volunteer_id INT,
    assigned_by_staff_id INT,            -- which admin/CC member made the assignment
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (assigned_volunteer_id) REFERENCES staff_users(id),
    FOREIGN KEY (assigned_by_staff_id) REFERENCES staff_users(id)
);

INSERT INTO help_requests (member_id, category, description, status)
SELECT id, 'elderly_help', 'Need someone to accompany an elderly parent to a hospital appointment next week.', 'open'
FROM members WHERE membership_id = 'KSIJ004';

-- ============================================================
-- FUNDS & DONATIONS
-- Anonymity rule: enforced by query design, not encryption —
-- any donor/public-facing SELECT must exclude member_id/donor_member_id.
-- ============================================================
CREATE TABLE funds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT,
    member_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    reason TEXT,
    purpose ENUM('medical') NULL DEFAULT NULL,
    medical_details TEXT,
    amount_needed DECIMAL(10,2) NOT NULL,
    amount_raised DECIMAL(10,2) DEFAULT 0,
    due_date DATE,
    status ENUM('pending_approval','active','completed') DEFAULT 'pending_approval',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id)
);

INSERT INTO funds (member_id, title, reason, amount_needed, amount_raised, due_date, status)
SELECT id, 'Urgent Surgery Support', 'Community member requires emergency surgery and has requested community support.', 300000.00, 45000.00, '2026-11-15', 'active'
FROM members WHERE membership_id = 'KSIJ003';

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

CREATE TABLE donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fund_id INT NOT NULL,
    donor_member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fund_id) REFERENCES funds(id),
    FOREIGN KEY (donor_member_id) REFERENCES members(id)
);

CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    type ENUM('credit','debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id)
);

INSERT INTO wallet_transactions (member_id, type, amount, reference)
SELECT id, 'credit', 1000.00, 'Office credit - Sukha deposit' FROM members WHERE membership_id = 'KSIJ001';

-- ============================================================
-- NOTIFICATIONS (in-app only — NOT email, see Phase 2 notes below)
-- recipient_type + recipient_id together identify WHO it's for:
-- 'member' -> members.id, 'staff' -> staff_users.id
-- ============================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('member','staff') NOT NULL,
    recipient_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Dummy notifications so the bell/panel isn't empty on first test
INSERT INTO notifications (recipient_type, recipient_id, title, message)
SELECT 'member', id, 'Request Status Updated', 'Your scholarship request has been approved by one guarantor.'
FROM members WHERE membership_id = 'KSIJ002';

INSERT INTO notifications (recipient_type, recipient_id, title, message)
SELECT 'staff', id, 'CC Member Meeting', 'There is a CC member meeting scheduled this Sunday at 5 PM.'
FROM staff_users WHERE username = 'ccmember1';
