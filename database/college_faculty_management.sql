CREATE DATABASE IF NOT EXISTS college_faculty_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE college_faculty_management;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS faculty_documents;
DROP TABLE IF EXISTS faculty_achievements;
DROP TABLE IF EXISTS publications;
DROP TABLE IF EXISTS lecture_schedule;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS faculty_profiles;
DROP TABLE IF EXISTS faculty_users;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS admins;

CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE faculty_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    employee_id VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    department VARCHAR(120) NOT NULL,
    designation VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE faculty_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT UNSIGNED NOT NULL,
    qualification TEXT,
    experience TEXT,
    research_interests TEXT,
    achievements LONGTEXT,
    certifications LONGTEXT,
    publications LONGTEXT,
    profile_photo VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profiles_faculty FOREIGN KEY (faculty_id) REFERENCES faculty_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT UNSIGNED NOT NULL,
    subject_name VARCHAR(150) NOT NULL,
    subject_code VARCHAR(50) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    credits INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_subjects_faculty FOREIGN KEY (faculty_id) REFERENCES faculty_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lecture_schedule (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED DEFAULT NULL,
    lecture_day VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room_number VARCHAR(50) NOT NULL,
    department VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_faculty FOREIGN KEY (faculty_id) REFERENCES faculty_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE publications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    publication_type VARCHAR(120) NOT NULL,
    publication_year YEAR NOT NULL,
    document_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_publications_faculty FOREIGN KEY (faculty_id) REFERENCES faculty_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE faculty_achievements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT UNSIGNED NOT NULL,
    achievement_title VARCHAR(255) NOT NULL,
    achievement_description TEXT,
    achievement_date DATE DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_achievements_faculty FOREIGN KEY (faculty_id) REFERENCES faculty_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(150) NOT NULL UNIQUE,
    hod_name VARCHAR(150) NOT NULL,
    faculty_count INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE faculty_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(30) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_documents_faculty FOREIGN KEY (faculty_id) REFERENCES faculty_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO admins (username, password, created_at) VALUES
('admin', SHA2(CONCAT('college-faculty-management-secret', '|', 'admin', '|', 'Admin@123'), 256), NOW());

INSERT INTO departments (department_name, hod_name, faculty_count) VALUES
('Computer Science', 'Dr. Meera Nair', 2),
('Mathematics', 'Dr. R. Srinivasan', 1),
('Physics', 'Dr. Kavita Rao', 1),
('Commerce', 'Dr. Anil Kumar', 0);

INSERT INTO faculty_users (full_name, employee_id, email, password, department, designation, phone, created_at) VALUES
('Dr. Asha Menon', 'FAC001', 'asha.menon@college.edu', SHA2(CONCAT('college-faculty-management-secret', '|', 'FAC001', '|', 'Faculty@123'), 256), 'Computer Science', 'Associate Professor', '9876500011', NOW()),
('Dr. Vikram Patel', 'FAC002', 'vikram.patel@college.edu', SHA2(CONCAT('college-faculty-management-secret', '|', 'FAC002', '|', 'Faculty@123'), 256), 'Mathematics', 'Assistant Professor', '9876500012', NOW()),
('Dr. Sneha Iyer', 'FAC003', 'sneha.iyer@college.edu', SHA2(CONCAT('college-faculty-management-secret', '|', 'FAC003', '|', 'Faculty@123'), 256), 'Physics', 'Professor', '9876500013', NOW());

INSERT INTO faculty_profiles (faculty_id, qualification, experience, research_interests, achievements, certifications, publications, profile_photo, updated_at) VALUES
(1, 'Ph.D. in Computer Science', '12 years', 'Machine learning, data systems', 'Best Teacher Award 2024\nResearch Grant Recipient', 'NPTEL Python Certification\nOracle Cloud Foundations', 'Published paper on AI-based learning systems', NULL, NOW()),
(2, 'M.Sc. Mathematics, Ph.D. pursuing', '8 years', 'Applied mathematics, statistics', 'Department topper mentor', 'Advanced Statistics Certification', 'Two journal publications in applied mathematics', NULL, NOW()),
(3, 'Ph.D. in Physics', '15 years', 'Quantum mechanics, nanomaterials', 'State-level innovation award', 'Research methodology workshop', 'Published in international journals', NULL, NOW());

INSERT INTO subjects (faculty_id, subject_name, subject_code, semester, credits, created_at) VALUES
(1, 'Data Structures', 'CS201', 'III', 4, NOW()),
(1, 'Database Management Systems', 'CS301', 'V', 4, NOW()),
(2, 'Engineering Mathematics', 'MA101', 'I', 3, NOW()),
(3, 'Modern Physics', 'PH202', 'IV', 4, NOW());

INSERT INTO lecture_schedule (faculty_id, subject_id, lecture_day, start_time, end_time, room_number, department, created_at) VALUES
(1, 1, 'Monday', '09:00:00', '10:00:00', 'A-101', 'Computer Science', NOW()),
(1, 2, 'Wednesday', '11:00:00', '12:00:00', 'A-202', 'Computer Science', NOW()),
(2, 3, 'Tuesday', '10:00:00', '11:00:00', 'B-104', 'Mathematics', NOW()),
(3, 4, 'Friday', '01:00:00', '02:00:00', 'C-303', 'Physics', NOW());

INSERT INTO publications (faculty_id, title, publication_type, publication_year, document_path, created_at) VALUES
(1, 'AI-Based Learning Systems in Higher Education', 'Journal Article', 2025, NULL, NOW()),
(2, 'Applied Statistics in Engineering Research', 'Conference Paper', 2024, NULL, NOW()),
(3, 'Nanomaterials and Quantum Applications', 'Book Chapter', 2025, NULL, NOW());

INSERT INTO faculty_achievements (faculty_id, achievement_title, achievement_description, achievement_date, created_at) VALUES
(1, 'Best Teacher Award', 'Recognized for outstanding classroom delivery and mentoring.', '2024-09-05', NOW()),
(2, 'Research Grant Recipient', 'Awarded grant for an applied mathematics project.', '2024-06-18', NOW()),
(3, 'Innovation Excellence Award', 'Honored for departmental innovation contribution.', '2024-11-22', NOW());
