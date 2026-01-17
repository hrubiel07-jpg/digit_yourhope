-- Ajouter des tables manquantes
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS view_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT,
    teacher_id INT,
    school_id INT,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT,
    teacher_id INT,
    school_id INT,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (parent_id, teacher_id, school_id)
);

-- Ajouter des colonnes manquantes
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS address TEXT AFTER bio;
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS city VARCHAR(100) AFTER address;
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS country VARCHAR(100) AFTER city;

ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL AFTER is_verified;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(100) AFTER verification_token;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_expires TIMESTAMP NULL AFTER reset_token;

-- Créer des index pour améliorer les performances
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_user_type ON users(user_type);
CREATE INDEX idx_teachers_specialization ON teachers(specialization);
CREATE INDEX idx_teachers_rating ON teachers(rating);
CREATE INDEX idx_schools_city ON schools(city);
CREATE INDEX idx_schools_school_type ON schools(school_type);
CREATE INDEX idx_appointments_date ON appointments(appointment_date);
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_teacher_services_teacher ON teacher_services(teacher_id);
CREATE INDEX idx_school_jobs_school ON school_jobs(school_id);
CREATE INDEX idx_teacher_applications_status ON teacher_applications(status);

-- Ajouter des contraintes de vérification
ALTER TABLE reviews ADD CONSTRAINT chk_rating_range CHECK (rating >= 1 AND rating <= 5);

-- Créer des déclencheurs pour les mises à jour automatiques
DELIMITER //

CREATE TRIGGER update_teacher_rating 
AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    DECLARE avg_rating DECIMAL(3,2);
    DECLARE total_count INT;
    
    SELECT AVG(rating), COUNT(*) 
    INTO avg_rating, total_count
    FROM reviews 
    WHERE teacher_id = NEW.teacher_id;
    
    UPDATE teachers 
    SET rating = COALESCE(avg_rating, 0), 
        total_reviews = total_count
    WHERE id = NEW.teacher_id;
END//

CREATE TRIGGER update_user_last_login
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.last_login IS NULL AND OLD.last_login IS NULL THEN
        SET NEW.last_login = CURRENT_TIMESTAMP;
    END IF;
END//

DELIMITER ;