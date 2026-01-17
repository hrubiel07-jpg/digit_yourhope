-- Création de la base de données
CREATE DATABASE IF NOT EXISTS digital_yourhope;
USE digital_yourhope;

-- Table des utilisateurs (commun à tous)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('school', 'teacher', 'parent') NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    verification_token VARCHAR(100),
    is_verified BOOLEAN DEFAULT FALSE
);

-- Table des écoles
CREATE TABLE schools (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    school_name VARCHAR(200) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    website VARCHAR(200),
    description TEXT,
    logo VARCHAR(255),
    established_year INT,
    school_type ENUM('public', 'private', 'international') DEFAULT 'private',
    accreditation VARCHAR(200),
    facilities TEXT,
    UNIQUE KEY unique_school_name (school_name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des enseignants
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    qualification VARCHAR(200) NOT NULL,
    specialization VARCHAR(200) NOT NULL,
    experience_years INT,
    hourly_rate DECIMAL(10,2),
    availability ENUM('full_time', 'part_time', 'weekends', 'flexible') DEFAULT 'flexible',
    bio TEXT,
    cv_path VARCHAR(255),
    certificates TEXT,
    subjects TEXT,
    teaching_levels TEXT,
    rating DECIMAL(3,2) DEFAULT 0.0,
    total_reviews INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des parents
CREATE TABLE parents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    child_name VARCHAR(150),
    child_age INT,
    child_grade VARCHAR(50),
    address TEXT,
    preferred_subjects TEXT,
    preferred_location VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des services des enseignants
CREATE TABLE teacher_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT,
    service_type ENUM('home_tutoring', 'td_correction', 'online_course', 'test_preparation') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2),
    duration VARCHAR(50),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Table des emplois dans les écoles
CREATE TABLE school_jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT,
    job_title VARCHAR(200) NOT NULL,
    job_description TEXT,
    requirements TEXT,
    subject VARCHAR(100),
    level VARCHAR(100),
    job_type ENUM('full_time', 'part_time', 'contract') NOT NULL,
    salary_range VARCHAR(100),
    application_deadline DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
);

-- Table des candidatures des enseignants
CREATE TABLE teacher_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT,
    job_id INT,
    application_letter TEXT,
    status ENUM('pending', 'reviewed', 'accepted', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES school_jobs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (teacher_id, job_id)
);

-- Table des avis et évaluations
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reviewer_id INT,
    reviewer_type ENUM('parent', 'school') NOT NULL,
    teacher_id INT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Table des rendez-vous
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT,
    teacher_id INT,
    service_id INT,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES teacher_services(id) ON DELETE SET NULL
);

-- Table des messages
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT,
    receiver_id INT,
    subject VARCHAR(200),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table de configuration des écoles
CREATE TABLE school_configurations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT,
    theme_color VARCHAR(7) DEFAULT '#3498db',
    logo VARCHAR(255),
    banner VARCHAR(255),
    welcome_message TEXT,
    contact_email VARCHAR(100),
    social_facebook VARCHAR(200),
    social_twitter VARCHAR(200),
    social_linkedin VARCHAR(200),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
);

-- Insertion de données de démonstration
INSERT INTO users (email, password, user_type, full_name, phone, is_verified) VALUES
('ecole@exemple.com', '$2y$10$YourHashHere', 'school', 'Lycée Excellence', '+221 77 123 4567', TRUE),
('prof@exemple.com', '$2y$10$YourHashHere', 'teacher', 'Marie Diop', '+221 76 234 5678', TRUE),
('parent@exemple.com', '$2y$10$YourHashHere', 'parent', 'Papa Ndiaye', '+221 70 345 6789', TRUE);