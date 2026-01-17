-- STRUCTURE COMPLÈTE DE LA BASE DE DONNÉES DIGITAL YOURHOPE
-- Exécuter ce fichier dans phpMyAdmin

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- TABLE: messaging_system
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `sender_type` enum('school','teacher','parent','admin') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `receiver_type` enum('school','teacher','parent','admin') NOT NULL,
  `conversation_id` varchar(50) NOT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_deleted_sender` tinyint(1) DEFAULT 0,
  `is_deleted_receiver` tinyint(1) DEFAULT 0,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sender_idx` (`sender_id`,`sender_type`),
  KEY `receiver_idx` (`receiver_id`,`receiver_type`),
  KEY `conversation_idx` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- TABLE: bulletin_system
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bulletins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `term` enum('trimestre1','trimestre2','trimestre3','semestre1','semestre2','annuel') NOT NULL,
  `class_id` int(11) NOT NULL,
  `academic_year` varchar(10) NOT NULL,
  `average` decimal(5,2) DEFAULT NULL,
  `rank` int(11) DEFAULT NULL,
  `total_students` int(11) DEFAULT NULL,
  `teacher_comment` text DEFAULT NULL,
  `principal_comment` text DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_idx` (`student_id`),
  KEY `class_idx` (`class_id`),
  KEY `year_term_idx` (`school_year`,`term`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bulletin_grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bulletin_id` int(11) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `coefficient` int(11) DEFAULT 1,
  `grade1` decimal(5,2) DEFAULT NULL,
  `grade2` decimal(5,2) DEFAULT NULL,
  `grade3` decimal(5,2) DEFAULT NULL,
  `grade4` decimal(5,2) DEFAULT NULL,
  `average` decimal(5,2) DEFAULT NULL,
  `appreciation` varchar(50) DEFAULT NULL,
  `teacher_comment` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bulletin_idx` (`bulletin_id`),
  FOREIGN KEY (`bulletin_id`) REFERENCES `bulletins`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- TABLE: payment_system
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `online_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `gateway` enum('wave','orange_money','mtn_money','paypal','stripe','bank_transfer') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'XOF',
  `payer_name` varchar(200) DEFAULT NULL,
  `payer_phone` varchar(20) DEFAULT NULL,
  `payer_email` varchar(100) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','cancelled','refunded') DEFAULT 'pending',
  `gateway_response` text DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_idx` (`transaction_id`),
  KEY `payment_idx` (`payment_id`),
  FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payment_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `gateway_name` varchar(50) NOT NULL,
  `api_key` text DEFAULT NULL,
  `api_secret` text DEFAULT NULL,
  `merchant_id` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `test_mode` tinyint(1) DEFAULT 1,
  `config_data` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `school_idx` (`school_id`),
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- TABLE: school_calendar
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `school_calendar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` enum('academic','administrative','holiday','exam','meeting','cultural','sport','other') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_all_day` tinyint(1) DEFAULT 1,
  `location` varchar(200) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#3498db',
  `visibility` enum('public','teachers','students','parents','admin') DEFAULT 'public',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `school_idx` (`school_id`),
  KEY `date_idx` (`start_date`,`end_date`),
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- TABLE: exam_registration
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `exam_type` enum('CEPE','BEPC','BAC','BTS','CAP','OTHER') NOT NULL,
  `academic_year` varchar(10) NOT NULL,
  `registration_period_start` date NOT NULL,
  `registration_period_end` date NOT NULL,
  `exam_date` date DEFAULT NULL,
  `center_name` varchar(200) DEFAULT NULL,
  `center_code` varchar(50) DEFAULT NULL,
  `status` enum('open','closed','completed') DEFAULT 'open',
  `total_registered` int(11) DEFAULT 0,
  `total_passed` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `school_exam_idx` (`school_id`,`exam_type`,`academic_year`),
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `exam_candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_registration_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `center_table_number` varchar(20) DEFAULT NULL,
  `status` enum('registered','absent','passed','failed','awaiting') DEFAULT 'registered',
  `score` decimal(5,2) DEFAULT NULL,
  `mention` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `certificate_delivered` tinyint(1) DEFAULT 0,
  `delivery_date` date DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_exam_idx` (`exam_registration_id`,`student_id`),
  KEY `registration_idx` (`registration_number`),
  FOREIGN KEY (`exam_registration_id`) REFERENCES `exam_registrations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- TABLE: whatsapp_integration
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `template_type` enum('bulletin','payment','event','exam','attendance','general') NOT NULL,
  `message_template` text NOT NULL,
  `variables` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `school_idx` (`school_id`),
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `whatsapp_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `recipient_type` enum('student','parent','teacher','all_parents','all_teachers','all_students') NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `recipient_phone` varchar(20) NOT NULL,
  `message_content` text NOT NULL,
  `status` enum('pending','sent','delivered','failed','read') DEFAULT 'pending',
  `message_id` varchar(100) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `school_status_idx` (`school_id`,`status`),
  KEY `recipient_idx` (`recipient_phone`),
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`template_id`) REFERENCES `whatsapp_templates`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- TABLE: email_templates
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `variables` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `school_idx` (`school_id`),
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- TABLE: file_export_logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `export_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `export_type` enum('excel','pdf','csv','word') NOT NULL,
  `document_type` enum('students_list','exam_candidates','bulletins','payments','teachers_list','classes_list') NOT NULL,
  `filters` text DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `school_export_idx` (`school_id`,`export_type`),
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- INSERTION DES DONNÉES DE BASE
-- --------------------------------------------------------
INSERT INTO `whatsapp_templates` (`school_id`, `template_name`, `template_type`, `message_template`, `variables`) VALUES
(1, 'Bulletin Scolaire', 'bulletin', 'Cher parent, le bulletin scolaire de {student_name} pour {term} {academic_year} est disponible. Moyenne: {average}/20, Rang: {rank}/{total}. Connectez-vous sur {school_portal} pour le télécharger.', 'student_name,term,academic_year,average,rank,total,school_portal'),
(1, 'Paiement Réussi', 'payment', 'Bonjour {parent_name}, le paiement de {amount} XOF pour {fee_name} a été reçu avec succès. Référence: {payment_ref}. Merci!', 'parent_name,amount,fee_name,payment_ref'),
(1, 'Inscription Examen', 'exam', 'Cher parent, {student_name} a été inscrit(e) à l''examen {exam_type} {academic_year}. Numéro d''inscription: {registration_number}. Date d''examen: {exam_date}.', 'student_name,exam_type,academic_year,registration_number,exam_date');

INSERT INTO `email_templates` (`school_id`, `template_name`, `subject`, `body`, `variables`) VALUES
(1, 'Bulletin Scolaire', 'Bulletin scolaire de {student_name} - {term} {academic_year}', '<h2>Bulletin Scolaire</h2><p>Cher parent,</p><p>Le bulletin scolaire de {student_name} pour {term} {academic_year} est disponible.</p><p>Moyenne: {average}/20<br>Rang: {rank} sur {total} élèves</p><p>Connectez-vous à votre espace parent pour télécharger le bulletin complet.</p><p>Cordialement,<br>Direction de {school_name}</p>', 'student_name,term,academic_year,average,rank,total,school_name'),
(1, 'Rappel Paiement', 'Rappel de paiement des frais scolaires', '<h2>Rappel de Paiement</h2><p>Cher parent,</p><p>Nous vous rappelons que le paiement des frais de {fee_name} d''un montant de {amount} XOF est attendu avant le {due_date}.</p><p>Vous pouvez effectuer le paiement en ligne sur votre espace parent.</p><p>Cordialement,<br>Service Financier de {school_name}</p>', 'fee_name,amount,due_date,school_name');

-- Données de test pour le calendrier
INSERT INTO `school_calendar` (`school_id`, `title`, `description`, `event_type`, `start_date`, `end_date`, `color`, `visibility`) VALUES
(1, 'Rentrée scolaire 2024-2025', 'Premier jour des classes', 'academic', '2024-09-02', '2024-09-02', '#2ecc71', 'public'),
(1, 'Vacances de Noël', 'Période de vacances', 'holiday', '2024-12-23', '2025-01-05', '#e74c3c', 'public'),
(1, 'Examen CEPE', 'Examen de fin d''études primaires', 'exam', '2025-06-10', '2025-06-10', '#9b59b6', 'public'),
(1, 'Réunion parents-professeurs', 'Réunion trimestrielle', 'meeting', '2024-10-15', '2024-10-15', '#3498db', 'parents');