-- Créer une base de données plus complète pour les écoles congolaises
USE digital_yourhope;

-- ============ TABLES POUR LA GESTION SCOLAIRE ============

-- Niveaux scolaires spécifiques au Congo
CREATE TABLE IF NOT EXISTS school_levels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT,
    level_name VARCHAR(50) NOT NULL,
    level_code VARCHAR(10) NOT NULL,
    cycle ENUM('Primaire', 'Secondaire I', 'Secondaire II') NOT NULL,
    order_num INT,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY unique_level (school_id, level_code)
);

-- Classes (CP1, CP2, 6ème, Terminale, etc.)
CREATE TABLE IF NOT EXISTS classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT,
    level_id INT,
    class_name VARCHAR(50) NOT NULL,
    class_code VARCHAR(10) NOT NULL,
    capacity INT,
    room_number VARCHAR(20),
    teacher_id INT,
    academic_year VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (level_id) REFERENCES school_levels(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- Matières scolaires
CREATE TABLE IF NOT EXISTS subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20),
    coefficient DECIMAL(3,1) DEFAULT 1.0,
    category ENUM('Lettres', 'Sciences', 'Techniques', 'Arts', 'Sport') DEFAULT 'Sciences',
    is_mandatory BOOLEAN DEFAULT TRUE,
    max_score DECIMAL(5,2) DEFAULT 20.00,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
);

-- Élèves
CREATE TABLE IF NOT EXISTS students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT,
    parent_id INT,
    matricule VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('M', 'F') NOT NULL,
    birth_date DATE,
    birth_place VARCHAR(100),
    nationality VARCHAR(100) DEFAULT 'Congolaise',
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    blood_group VARCHAR(5),
    allergies TEXT,
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    photo VARCHAR(255),
    enrollment_date DATE,
    current_class_id INT,
    status ENUM('active', 'suspended', 'graduated', 'transferred', 'expelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE SET NULL,
    FOREIGN KEY (current_class_id) REFERENCES classes(id) ON DELETE SET NULL
);

-- Inscriptions annuelles
CREATE TABLE IF NOT EXISTS enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    class_id INT,
    academic_year VARCHAR(20),
    enrollment_date DATE,
    tuition_fee DECIMAL(10,2),
    discount DECIMAL(10,2) DEFAULT 0.00,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    balance DECIMAL(10,2) DEFAULT 0.00,
    payment_status ENUM('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending',
    status ENUM('active', 'transferred', 'withdrawn') DEFAULT 'active',
    notes TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- Emplois du temps
CREATE TABLE IF NOT EXISTS schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT,
    class_id INT,
    subject_id INT,
    teacher_id INT,
    day_of_week ENUM('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50),
    academic_year VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Notes des élèves
CREATE TABLE IF NOT EXISTS grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    subject_id INT,
    class_id INT,
    teacher_id INT,
    academic_year VARCHAR(20),
    term ENUM('Trimestre 1', 'Trimestre 2', 'Trimestre 3') NOT NULL,
    type ENUM('Devoir', 'Composition', 'Examen', 'Oral', 'Pratique') DEFAULT 'Devoir',
    score DECIMAL(5,2),
    max_score DECIMAL(5,2) DEFAULT 20.00,
    coefficient DECIMAL(3,1) DEFAULT 1.0,
    comment TEXT,
    recorded_date DATE,
    recorded_by INT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Bulletins scolaires
CREATE TABLE IF NOT EXISTS report_cards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    class_id INT,
    academic_year VARCHAR(20),
    term ENUM('Trimestre 1', 'Trimestre 2', 'Trimestre 3') NOT NULL,
    average_score DECIMAL(5,2),
    class_rank INT,
    total_students INT,
    teacher_comment TEXT,
    principal_comment TEXT,
    attendance_days INT,
    absence_days INT,
    tardiness_days INT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    generated_by INT,
    is_published BOOLEAN DEFAULT FALSE,
    published_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Absences
CREATE TABLE IF NOT EXISTS absences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    class_id INT,
    date DATE NOT NULL,
    reason TEXT,
    justified BOOLEAN DEFAULT FALSE,
    justified_by INT,
    justified_at TIMESTAMP NULL,
    recorded_by INT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (justified_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Frais de scolarité
CREATE TABLE IF NOT EXISTS school_fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT,
    fee_name VARCHAR(200) NOT NULL,
    fee_type ENUM('Inscription', 'Scolarité', 'Bibliothèque', 'Laboratoire', 'Sport', 'Autre') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'FCFA',
    academic_year VARCHAR(20),
    level_id INT,
    is_mandatory BOOLEAN DEFAULT TRUE,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (level_id) REFERENCES school_levels(id) ON DELETE CASCADE
);

-- Paiements
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    fee_id INT,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'FCFA',
    payment_date DATE,
    payment_method ENUM('Espèces', 'Chèque', 'Virement', 'Mobile Money') DEFAULT 'Espèces',
    receipt_number VARCHAR(50) UNIQUE,
    received_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_id) REFERENCES school_fees(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Événements scolaires
CREATE TABLE IF NOT EXISTS school_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT,
    event_type ENUM('Fête', 'Réunion', 'Examen', 'Vacances', 'Formation', 'Autre') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE,
    start_time TIME,
    end_time TIME,
    location VARCHAR(200),
    target_audience ENUM('Tous', 'Primaire', 'Secondaire', 'Enseignants', 'Parents') DEFAULT 'Tous',
    is_published BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============ PERSONNALISATION DES ÉCOLES ============

-- Thèmes et couleurs personnalisées
ALTER TABLE school_configurations
ADD COLUMN IF NOT EXISTS primary_color VARCHAR(7) DEFAULT '#3498db',
ADD COLUMN IF NOT EXISTS secondary_color VARCHAR(7) DEFAULT '#2ecc71',
ADD COLUMN IF NOT EXISTS accent_color VARCHAR(7) DEFAULT '#e74c3c',
ADD COLUMN IF NOT EXISTS text_color VARCHAR(7) DEFAULT '#2c3e50',
ADD COLUMN IF NOT EXISTS background_color VARCHAR(7) DEFAULT '#f8f9fa';

-- Informations financières
ALTER TABLE school_configurations
ADD COLUMN IF NOT EXISTS currency VARCHAR(10) DEFAULT 'FCFA',
ADD COLUMN IF NOT EXISTS currency_symbol VARCHAR(10) DEFAULT 'FCFA',
ADD COLUMN IF NOT EXISTS tax_rate DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS bank_name VARCHAR(200),
ADD COLUMN IF NOT EXISTS bank_account VARCHAR(100),
ADD COLUMN IF NOT EXISTS payment_instructions TEXT;

-- Informations académiques spécifiques au Congo
ALTER TABLE school_configurations
ADD COLUMN IF NOT EXISTS education_system ENUM('Congolais', 'Français', 'Anglais', 'Bilingue') DEFAULT 'Congolais',
ADD COLUMN IF NOT EXISTS academic_calendar JSON,
ADD COLUMN IF NOT EXISTS grading_system TEXT,
ADD COLUMN IF NOT EXISTS exam_system TEXT;

-- ============ DONNÉES PAR DÉFAUT POUR LE CONGO ============

-- Niveaux scolaires congolais
INSERT INTO school_levels (school_id, level_name, level_code, cycle, order_num) VALUES
(NULL, 'Cours Préparatoire 1', 'CP1', 'Primaire', 1),
(NULL, 'Cours Préparatoire 2', 'CP2', 'Primaire', 2),
(NULL, 'Cours Élémentaire 1', 'CE1', 'Primaire', 3),
(NULL, 'Cours Élémentaire 2', 'CE2', 'Primaire', 4),
(NULL, 'Cours Moyen 1', 'CM1', 'Primaire', 5),
(NULL, 'Cours Moyen 2', 'CM2', 'Primaire', 6),
(NULL, 'Sixième', '6ème', 'Secondaire I', 7),
(NULL, 'Cinquième', '5ème', 'Secondaire I', 8),
(NULL, 'Quatrième', '4ème', 'Secondaire I', 9),
(NULL, 'Troisième', '3ème', 'Secondaire I', 10),
(NULL, 'Seconde', '2nde', 'Secondaire II', 11),
(NULL, 'Première', '1ère', 'Secondaire II', 12),
(NULL, 'Terminale', 'Tle', 'Secondaire II', 13);

-- Matières scolaires congolaises
INSERT INTO subjects (school_id, subject_name, subject_code, coefficient, category) VALUES
(NULL, 'Français', 'FR', 4.0, 'Lettres'),
(NULL, 'Anglais', 'AN', 2.0, 'Lettres'),
(NULL, 'Mathématiques', 'MA', 4.0, 'Sciences'),
(NULL, 'Physique-Chimie', 'PC', 3.0, 'Sciences'),
(NULL, 'Sciences de la Vie et de la Terre', 'SVT', 2.0, 'Sciences'),
(NULL, 'Histoire-Géographie', 'HG', 2.0, 'Lettres'),
(NULL, 'Éducation Civique', 'EC', 1.0, 'Lettres'),
(NULL, 'Philosophie', 'PH', 2.0, 'Lettres'),
(NULL, 'Éducation Physique et Sportive', 'EPS', 1.0, 'Sport'),
(NULL, 'Arts Plastiques', 'ART', 1.0, 'Arts');

-- Frais scolaires par défaut
INSERT INTO school_fees (school_id, fee_name, fee_type, amount, currency, academic_year) VALUES
(NULL, 'Frais d\'inscription', 'Inscription', 50000.00, 'FCFA', '2024-2025'),
(NULL, 'Frais de scolarité primaire', 'Scolarité', 150000.00, 'FCFA', '2024-2025'),
(NULL, 'Frais de scolarité secondaire', 'Scolarité', 200000.00, 'FCFA', '2024-2025'),
(NULL, 'Frais bibliothèque', 'Bibliothèque', 10000.00, 'FCFA', '2024-2025'),
(NULL, 'Frais laboratoire', 'Laboratoire', 15000.00, 'FCFA', '2024-2025');

-- Événements scolaires congolais
INSERT INTO school_events (school_id, event_type, title, start_date, end_date, target_audience) VALUES
(NULL, 'Vacances', 'Vacances de Noël', '2024-12-20', '2025-01-05', 'Tous'),
(NULL, 'Examen', 'Examen du 1er Trimestre', '2024-11-15', '2024-11-20', 'Tous'),
(NULL, 'Fête', 'Fête de l\'Indépendance', '2024-08-15', '2024-08-15', 'Tous'),
(NULL, 'Réunion', 'Réunion parents-professeurs', '2024-10-10', '2024-10-10', 'Parents');

-- Créer une vue pour les statistiques des écoles
CREATE VIEW school_statistics AS
SELECT 
    s.id,
    s.school_name,
    s.city,
    s.school_type,
    COUNT(DISTINCT st.id) as total_students,
    COUNT(DISTINCT t.id) as total_teachers,
    COUNT(DISTINCT cl.id) as total_classes,
    COUNT(DISTINCT sj.id) as active_jobs,
    AVG(tr.rating) as avg_rating
FROM schools s
LEFT JOIN students st ON s.id = st.school_id AND st.status = 'active'
LEFT JOIN teachers t ON EXISTS (
    SELECT 1 FROM school_jobs sj2 
    WHERE sj2.school_id = s.id 
    AND EXISTS (
        SELECT 1 FROM teacher_applications ta 
        WHERE ta.job_id = sj2.id 
        AND ta.status = 'accepted' 
        AND ta.teacher_id = t.id
    )
)
LEFT JOIN classes cl ON s.id = cl.school_id AND cl.is_active = TRUE
LEFT JOIN school_jobs sj ON s.id = sj.school_id AND sj.is_active = TRUE
LEFT JOIN teachers tr ON s.id = tr.id
GROUP BY s.id, s.school_name, s.city, s.school_type;