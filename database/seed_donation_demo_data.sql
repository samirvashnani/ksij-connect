-- KSIJ Connect: donation ledger presentation data.
-- Run the entire file in ONE session, with the project database selected.
-- Creates up to 24 projects, 80 fictional members and 600 demo payments.
-- Run seed_health_centre.sql first, or create one project with a valid image.
-- Requires an active admin and the existing donation tables.
-- If categories are installed, run the category UPDATE portion of query.sql
-- after this seed to classify its newly created sample projects. Do not rerun
-- an already-applied ALTER TABLE or CREATE INDEX statement.
-- Does not modify existing records, credit wallets, or record real collections.
-- All payments use payment_mode='demo'; the actual-received total stays unchanged.
-- Stable IDs make sequential reruns skip records already inserted by this seed.
-- Use only in the presentation database. Take a backup before importing.
-- The same existing illustration is reused for sample projects, not a claim
-- about the facilities or services of any real initiative.
-- The image is copied into each project; large images increase database size.

SET NAMES utf8mb4;
SET @demo_date = '2026-10-04';
SET @demo_admin = (
    SELECT id FROM staff_users
    WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1
);
SET @demo_image_project = (
    SELECT id FROM donation_projects
    WHERE image_mime IN ('image/jpeg','image/png','image/webp')
        AND OCTET_LENGTH(image_data) > 0
    ORDER BY (title = 'KSIJ Health Centre Palagali') DESC, id
    LIMIT 1
);
SET @demo_ready = (@demo_admin IS NOT NULL AND @demo_image_project IS NOT NULL);

SELECT CASE
    WHEN @demo_admin IS NULL THEN 'SKIPPED: create an active admin first.'
    WHEN @demo_image_project IS NULL THEN 'SKIPPED: run seed_health_centre.sql or create a project with an image first.'
    ELSE 'Ready: demo records will be inserted; existing records will be preserved.'
END AS seed_status;

DROP TEMPORARY TABLE IF EXISTS ksij_demo_numbers;
DROP TEMPORARY TABLE IF EXISTS ksij_demo_projects;
DROP TEMPORARY TABLE IF EXISTS ksij_demo_project_map;

-- Derived digit tables avoid recursive-query requirements and temporary-table
-- reopening restrictions, so the seed works on older MySQL installations too.
CREATE TEMPORARY TABLE ksij_demo_numbers (n INT NOT NULL PRIMARY KEY);
INSERT INTO ksij_demo_numbers (n)
SELECT 1 + u.d + 10*t.d + 100*h.d
FROM (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) u
CROSS JOIN (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) t
CROSS JOIN (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) h;

CREATE TEMPORARY TABLE ksij_demo_projects (
    n INT NOT NULL PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    purpose VARCHAR(600) NOT NULL,
    quote VARCHAR(300) NOT NULL
) DEFAULT CHARSET=utf8mb4;

INSERT INTO ksij_demo_projects (n,title,purpose,quote) VALUES
(1,'[DEMO] Community Health Support','Support affordable community healthcare and patient assistance.','Compassion begins when we choose to care.'),
(2,'[DEMO] Student Scholarship Appeal','Support students facing difficulty meeting education expenses.','An opportunity to learn can change a lifetime.'),
(3,'[DEMO] Family Ration Support','Support essential food supplies for families during difficult periods.','A caring community makes room at every table.'),
(4,'[DEMO] Emergency Medical Assistance','Support families coping with unexpected medical expenses.','Stand beside a family when they need it most.'),
(5,'[DEMO] Elderly Care Programme','Support practical assistance and wellbeing activities for elderly residents.','Respect grows stronger through everyday care.'),
(6,'[DEMO] School Essentials Drive','Support school supplies and learning materials for students.','Small essentials can open big possibilities.'),
(7,'[DEMO] Community Learning Centre','Support a shared space for learning and educational activities.','Build a place where curiosity can grow.'),
(8,'[DEMO] Clean Drinking Water','Support community access to safe drinking water.','Every family deserves a healthier tomorrow.'),
(9,'[DEMO] Mobility Assistance','Support people who need assistance with mobility and independence.','Help someone take their next step with dignity.'),
(10,'[DEMO] Youth Skills Development','Support practical skills and career-readiness opportunities for young people.','Invest in potential, encourage independence.'),
(11,'[DEMO] Mother and Child Wellbeing','Support community wellbeing initiatives for mothers and children.','Care today can nurture a stronger tomorrow.'),
(12,'[DEMO] Community Kitchen','Support shared meals and community food assistance.','A meal shared is a community strengthened.'),
(13,'[DEMO] Education Continuity Support','Support students at risk of interrupting their studies.','Keep a dream of education moving forward.'),
(14,'[DEMO] Disaster Relief Reserve','Support community preparedness and assistance during emergencies.','Preparedness is another form of compassion.'),
(15,'[DEMO] Vision Care Initiative','Support eye-care awareness and assistance for those in need.','Help someone see new possibilities.'),
(16,'[DEMO] Patient Transport Assistance','Support travel assistance for people attending medical appointments.','The journey to care should not be made alone.'),
(17,'[DEMO] Community Library','Support books, reading resources and shared learning.','A book can begin a new chapter in life.'),
(18,'[DEMO] Accessible Community Spaces','Support improvements that make community participation easier.','Belonging begins with access for everyone.'),
(19,'[DEMO] Winter Essentials Appeal','Support seasonal clothing and household essentials.','Warmth is a simple way to show we care.'),
(20,'[DEMO] Digital Learning Support','Support access to digital learning resources and skills.','Connect a learner with a world of opportunity.'),
(21,'[DEMO] Family Welfare Assistance','Support families navigating temporary financial hardship.','Together, a difficult season becomes easier.'),
(22,'[DEMO] Community Wellness Camp','Support community health awareness and wellbeing activities.','A healthier community starts with shared care.'),
(23,'[DEMO] Higher Education Support','Support learners pursuing further education and training.','Let ambition find the support it deserves.'),
(24,'[DEMO] Neighbourhood Support Fund','Support practical, locally coordinated community assistance.','Strong communities begin with helping a neighbour.');

START TRANSACTION;

INSERT INTO members
    (membership_id,full_name,email,phone,area,membership_status,fees_due,
     fees_last_paid_date,renewal_date,payment_link,wallet_balance,created_at)
SELECT
    CONCAT('DEMO-DON-',LPAD(seq.n,4,'0')),
    CONCAT('[DEMO] ',
        ELT(MOD(seq.n-1,8)+1,'Aamir','Fatima','Hasan','Zainab','Mehdi','Sakina','Ali','Maryam'),
        ' ',ELT(FLOOR((seq.n-1)/8)+1,'Raza','Kazmi','Hussain','Abidi','Jafferi','Naqvi','Rizvi','Zaidi','Merchant','Syed')),
    CONCAT('ksij.donation.demo.',LPAD(seq.n,4,'0'),'@example.invalid'),
    NULL,
    ELT(MOD(seq.n-1,6)+1,'Palagali','Mumbai','Palghar','Thane','Mumbra','Navi Mumbai'),
    'active',0.00,
    DATE_SUB(@demo_date,INTERVAL 150 DAY),
    DATE_ADD(@demo_date,INTERVAL 365 DAY),
    NULL,0.00,
    TIMESTAMP(DATE_SUB(@demo_date,INTERVAL 180 DAY),'09:00:00')
FROM ksij_demo_numbers seq
WHERE seq.n<=80 AND @demo_ready=1
    AND NOT EXISTS (
        SELECT 1 FROM members existing
        WHERE existing.membership_id=CONCAT('DEMO-DON-',LPAD(seq.n,4,'0'))
           OR existing.email=CONCAT('ksij.donation.demo.',LPAD(seq.n,4,'0'),'@example.invalid')
    );
SET @demo_members_added = ROW_COUNT();

INSERT INTO donation_projects
    (title,description,quote,image_data,image_mime,is_active,created_by,created_at)
SELECT
    catalog.title,
    CONCAT('PRESENTATION DATA ONLY. This is a fictional donation project, not a live fundraising appeal.',CHAR(10),CHAR(10),
        catalog.purpose,
        CHAR(10),CHAR(10),'This entry demonstrates project browsing, donation history and receipt records. The illustration is reused for demonstration and does not depict this project.'),
    catalog.quote,
    source.image_data,source.image_mime,
    CASE WHEN MOD(catalog.n,6)=0 THEN 0 ELSE 1 END,
    @demo_admin,
    TIMESTAMP(DATE_SUB(@demo_date,INTERVAL (160+catalog.n) DAY),'10:00:00')
FROM ksij_demo_projects catalog
JOIN donation_projects source ON source.id=@demo_image_project
WHERE @demo_ready=1 AND NOT EXISTS (
    SELECT 1 FROM donation_projects existing WHERE existing.title=catalog.title
);
SET @demo_projects_added = ROW_COUNT();

-- Resolve actual IDs instead of assuming any AUTO_INCREMENT starting value.
CREATE TEMPORARY TABLE ksij_demo_project_map (n INT NOT NULL PRIMARY KEY, project_id INT UNSIGNED NOT NULL);
INSERT INTO ksij_demo_project_map (n,project_id)
SELECT catalog.n,MIN(project.id)
FROM ksij_demo_projects catalog
JOIN donation_projects project ON project.title=catalog.title
    AND project.description LIKE 'PRESENTATION DATA ONLY.%'
GROUP BY catalog.n;

INSERT INTO donation_payments
    (project_id,project_title,member_id,donor_name,membership_id,amount,
     payment_id,submission_token,payment_mode,external_reference,notes,
     received_on,recorded_by,created_at)
SELECT
    project.id,project.title,demo_member.id,demo_member.full_name,demo_member.membership_id,
    CAST(ELT(MOD(seq.n*7,10)+1,250,500,750,1000,1500,2500,5000,7500,10000,25000) AS DECIMAL(10,2)),
    CONCAT('DEMO-SEED-DON-',LPAD(seq.n,6,'0')),
    SHA2(CONCAT('KSIJ-DONATION-PRESENTATION-V1-',seq.n),256),
    'demo','',
    CONCAT('Synthetic presentation payment #',seq.n,'. No money was collected. Seed: KSIJ-DONATION-PRESENTATION-V1.'),
    DATE_SUB(@demo_date,INTERVAL MOD(seq.n*17,120) DAY),
    NULL,
    TIMESTAMP(DATE_SUB(@demo_date,INTERVAL MOD(seq.n*17,120) DAY),
        MAKETIME(9+MOD(seq.n,10),MOD(seq.n*13,60),MOD(seq.n*7,60)))
FROM ksij_demo_numbers seq
JOIN ksij_demo_project_map mapping ON mapping.n=MOD(seq.n-1,24)+1
JOIN donation_projects project ON project.id=mapping.project_id
JOIN members demo_member
    ON demo_member.membership_id=CONCAT('DEMO-DON-',LPAD(MOD(FLOOR((seq.n-1)/24)*7+MOD(seq.n-1,24)*3,80)+1,4,'0'))
    AND demo_member.email=CONCAT('ksij.donation.demo.',LPAD(MOD(FLOOR((seq.n-1)/24)*7+MOD(seq.n-1,24)*3,80)+1,4,'0'),'@example.invalid')
    AND demo_member.full_name LIKE '[DEMO] %'
WHERE @demo_ready=1 AND NOT EXISTS (
    SELECT 1 FROM donation_payments existing
    WHERE existing.payment_id=CONCAT('DEMO-SEED-DON-',LPAD(seq.n,6,'0'))
       OR existing.submission_token=SHA2(CONCAT('KSIJ-DONATION-PRESENTATION-V1-',seq.n),256)
)
ORDER BY seq.n;
SET @demo_payments_added = ROW_COUNT();

COMMIT;

SELECT @demo_members_added AS members_added_this_run,
       @demo_projects_added AS projects_added_this_run,
       @demo_payments_added AS payments_added_this_run;

SELECT COUNT(*) AS demo_payment_count,
       COALESCE(SUM(amount),0) AS simulated_donations_inr,
       MIN(received_on) AS earliest_demo_date,
       MAX(received_on) AS latest_demo_date
FROM donation_payments
WHERE payment_id LIKE 'DEMO-SEED-DON-%'
    AND notes LIKE '%Seed: KSIJ-DONATION-PRESENTATION-V1.';

SELECT project.title,project.is_active,
       COUNT(payment.id) AS demo_payments,
       COALESCE(SUM(payment.amount),0) AS simulated_donations_inr
FROM ksij_demo_project_map mapping
JOIN donation_projects project ON project.id=mapping.project_id
LEFT JOIN donation_payments payment ON payment.project_id=project.id
    AND payment.payment_id LIKE 'DEMO-SEED-DON-%'
    AND payment.notes LIKE '%Seed: KSIJ-DONATION-PRESENTATION-V1.'
GROUP BY project.id,project.title,project.is_active
ORDER BY project.title;

DROP TEMPORARY TABLE ksij_demo_project_map;
DROP TEMPORARY TABLE ksij_demo_projects;
DROP TEMPORARY TABLE ksij_demo_numbers;
