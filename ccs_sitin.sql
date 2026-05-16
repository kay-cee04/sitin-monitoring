CREATE DATABASE IF NOT EXISTS ccs_sitin;
USE ccs_sitin;

-- ============================================================
--  TABLE: students
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    id_number       VARCHAR(20)  NOT NULL UNIQUE,
    lastname        VARCHAR(100) NOT NULL,
    firstname       VARCHAR(100) NOT NULL,
    middlename      VARCHAR(100) DEFAULT '',
    course          VARCHAR(20)  NOT NULL,
    year_level      TINYINT      NOT NULL DEFAULT 1,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    address         VARCHAR(255) DEFAULT '',
    session         INT          NOT NULL DEFAULT 30,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
--  TABLE: admins
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
--  TABLE: announcements
-- ============================================================
CREATE TABLE IF NOT EXISTS announcements (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    admin_name  VARCHAR(100) NOT NULL DEFAULT 'CCS Admin',
    content     TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
--  TABLE: reservations
-- ============================================================
CREATE TABLE IF NOT EXISTS reservations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    student_id      INT          NOT NULL,
    id_number       VARCHAR(20)  NOT NULL,
    purpose         VARCHAR(255) NOT NULL,
    laboratory      VARCHAR(50)  NOT NULL,
    time_in         TIME         DEFAULT NULL,
    date            DATE         DEFAULT NULL,
    status          ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
--  TABLE: sit_in_history  (login/logout records)
-- ============================================================
CREATE TABLE IF NOT EXISTS sit_in_history (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    student_id      INT         NOT NULL,
    id_number       VARCHAR(20) NOT NULL,
    fullname        VARCHAR(255)NOT NULL,
    sit_purpose     VARCHAR(255)NOT NULL,
    laboratory      VARCHAR(50) NOT NULL,
    login_time      DATETIME    DEFAULT NULL,
    logout_time     DATETIME    DEFAULT NULL,
    date            DATE        NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
--  TABLE: notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT  NOT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
--  TABLE: feedback
-- ============================================================
CREATE TABLE IF NOT EXISTS feedback (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sitin_id        INT         NOT NULL,
    student_id      INT         NOT NULL,
    admin_feedback  TEXT        NOT NULL,
    admin_name      VARCHAR(100)DEFAULT 'CCS Admin',
    created_at      TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sitin_id) REFERENCES sit_in_history(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
--  SAMPLE DATA
-- ============================================================

-- Admin account
-- username: admin | password: admin123
INSERT INTO admins (username, password) VALUES
('admin', 'admin123');

-- Sample student accounts (password for all: Password123)
-- password hash for "Password123"
INSERT INTO students (id_number, lastname, firstname, middlename, course, year_level, email, password, address, session) VALUES
('12345678', 'Dela Cruz',  'Juan',     'Santos',  'BSIT', 1, 'juan@uc.edu.ph',    '$2y$10$TKh8H1.PyfcAawKIhFCDn.LwPPMRAEnw8N2vFhzGQKmI8qOuE7Cme', 'Cebu City',   30),
('2024-00002', 'Reyes',      'Maria',    'Garcia',  'BSCS', 2, 'maria@uc.edu.ph',   '$2y$10$TKh8H1.PyfcAawKIhFCDn.LwPPMRAEnw8N2vFhzGQKmI8qOuE7Cme', 'Mandaue City',30),
('2024-00003', 'Santos',     'Jose',     'Ramos',   'BSIT', 3, 'jose@uc.edu.ph',    '$2y$10$TKh8H1.PyfcAawKIhFCDn.LwPPMRAEnw8N2vFhzGQKmI8qOuE7Cme', 'Lapu-Lapu',   30),
('2024-00004', 'Flores',     'Ana',      'Torres',  'BSIT',  1, 'ana@uc.edu.ph',     '$2y$10$TKh8H1.PyfcAawKIhFCDn.LwPPMRAEnw8N2vFhzGQKmI8qOuE7Cme', 'Talisay City',30),
('23784630',   'Sarmiento',  'Kathleen', 'Daclan',  'BSIT', 3, 'daclankath.23@gmail.com', '$2y$10$TKh8H1.PyfcAawKIhFCDn.LwPPMRAEnw8N2vFhzGQKmI8qOuE7Cme', 'Carcar', 30);

-- Sample announcements
INSERT INTO announcements (admin_name, content, created_at) VALUES
('CCS Admin', NULL, '2026-02-11 08:00:00'),
('CCS Admin', 'Important Announcement We are excited to announce the launch of our new website! 🎉 Explore our latest products and services now!', '2024-05-08 10:30:00');

-- Sample sit-in history
INSERT INTO sit_in_history (student_id, id_number, fullname, sit_purpose, laboratory, login_time, logout_time, date) VALUES
(1, '12345678', 'Kinsa Ka',     'C Programming',    '524', '2026-04-11 08:00:00', '2026-04-11 10:00:00', CURDATE()),
(2, '23456789', 'Padayon Ta',        'Web Development',  '526', '2026-04-11 09:00:00', '2026-04-11 11:00:00', CURDATE()),
(3, '34567890', 'Hay Nako',         'Database Systems', '524', '2026-04-11 13:00:00', '2026-04-11 15:00:00', CURDATE());

-- Add type column to notifications table
ALTER TABLE notifications ADD COLUMN type VARCHAR(50) DEFAULT NULL;

-- Add indexes for better performance
ALTER TABLE notifications ADD INDEX idx_student_read (student_id, is_read);
ALTER TABLE notifications ADD INDEX idx_created (created_at);

-- Add profile_photo column to students table if not exists
ALTER TABLE students ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL;

-- Add profile_photo column to testimonials table
ALTER TABLE testimonials ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL;

-- Create testimonials table if not exists
CREATE TABLE IF NOT EXISTS testimonials (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    fullname    VARCHAR(255) NOT NULL,
    course      VARCHAR(100) DEFAULT '',
    message     TEXT NOT NULL,
    rating      TINYINT(1) DEFAULT 5,
    profile_photo VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Create system_settings table for reservation toggle
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Enable reservations by default
INSERT INTO system_settings (setting_key, setting_value) VALUES ('reservations_open', '1')
ON DUPLICATE KEY UPDATE setting_value = '1';

-- Create lab_software table
CREATE TABLE IF NOT EXISTS lab_software (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    lab_name    VARCHAR(100) NOT NULL,
    software    VARCHAR(255) NOT NULL,
    category    VARCHAR(100) DEFAULT 'General',
    is_available TINYINT(1) DEFAULT 1,
    added_by    VARCHAR(100) DEFAULT 'Admin',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample software data
INSERT INTO lab_software (lab_name, software, category) VALUES
('Lab 517', 'Visual Studio Code', 'Development'),
('Lab 517', 'IntelliJ IDEA', 'Development'),
('Lab 517', 'MySQL Workbench', 'Database'),
('Lab 524', 'Adobe Photoshop', 'Design'),
('Lab 524', 'Figma', 'Design'),
('Lab 526', 'Python 3.11', 'Development'),
('Lab 526', 'Jupyter Notebook', 'Development'),
('Lab 528', 'Android Studio', 'Mobile Development'),
('Lab 530', 'Cisco Packet Tracer', 'Networking'),
('Lab 542', 'VMware Workstation', 'Virtualization'),
('Lab 544', 'Microsoft Office Suite', 'Productivity');

-- ============================================================
--  LOGIN CREDENTIALS SUMMARY
-- ============================================================
--
--  STUDENT LOGINS (use ID Number + password):
--  ┌─────────────────┬───────────────┬──────────────┐
--  │   ID Number     │   Full Name   │   Password   │
--  ├─────────────────┼───────────────┼──────────────┤
--  │ 2024-00001      │ Juan Dela Cruz│ Password123  │
--  │ 2024-00002      │ Maria Reyes   │ Password123  │
--  │ 2024-00003      │ Jose Santos   │ Password123  │
--  │ 2024-00004      │ Ana Flores    │ Password123  │
--  │ 23784630        │ Kathleen      │ Password123  │
--  └─────────────────┴───────────────┴──────────────┘
--
--  ADMIN LOGIN:
--  ┌──────────────┬─────────────┐
--  │   Username   │  Password   │
--  ├──────────────┼─────────────┤
--  │    admin     │   admin123  │
--  └──────────────┴─────────────┘
--
-- ============================================================