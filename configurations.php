<?php
// Utiliser l'autoloader central
require_once __DIR__ . '/../../autoload.php';

// Inclure la configuration de l'école
require_once __DIR__ . '/../../includes/school_config.php';

// Vérifier que l'utilisateur est une école
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'school') {
    header('Location: ' . SITE_URL . 'auth/login.php');
    exit();
}

// Récupérer la connexion PDO
if (!isset($pdo)) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
            DB_USER, 
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch(PDOException $e) {
        error_log("Erreur de connexion à la base de données: " . $e->getMessage());
        die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
    }
}

$user_id = $_SESSION['user_id'];

// Récupérer les infos complètes de l'école
$stmt = $pdo->prepare("SELECT s.*, u.full_name, u.email as user_email FROM schools s JOIN users u ON s.user_id = u.id WHERE s.user_id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();

// Récupérer la configuration actuelle de l'école
$school_config = getSchoolConfig($school['id']);

// Décoder les systèmes de notation et calendrier
$grading_system = json_decode($school_config['grading_system'] ?? '[]', true);
$academic_calendar = json_decode($school_config['academic_calendar'] ?? '[]', true);

// Valeurs par défaut si vides
if (empty($grading_system)) {
    $grading_system = [
        'excellent' => ['min' => 16, 'max' => 20, 'mention' => 'Excellent'],
        'tres_bien' => ['min' => 14, 'max' => 15.99, 'mention' => 'Très Bien'],
        'bien' => ['min' => 12, 'max' => 13.99, 'mention' => 'Bien'],
        'assez_bien' => ['min' => 10, 'max' => 11.99, 'mention' => 'Assez Bien'],
        'passable' => ['min' => 8, 'max' => 9.99, 'mention' => 'Passable'],
        'insuffisant' => ['min' => 0, 'max' => 7.99, 'mention' => 'Insuffisant']
    ];
}

if (empty($academic_calendar)) {
    $current_year = date('Y');
    $academic_calendar = [
        'trimestre1' => [
            'start' => $current_year . '-09-02',
            'end' => $current_year . '-12-20',
            'vacations' => []
        ],
        'trimestre2' => [
            'start' => ($current_year + 1) . '-01-06',
            'end' => ($current_year + 1) . '-03-28',
            'vacations' => []
        ],
        'trimestre3' => [
            'start' => ($current_year + 1) . '-03-31',
            'end' => ($current_year + 1) . '-06-30',
            'vacations' => []
        ]
    ];
}

// Messages
$message = '';
$error = '';

// Traitement du formulaire de configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_config') {
        // 1. Récupérer les informations de l'école (NOUVEAU - manquant dans notre version)
        $school_data = [
            'school_name' => trim($_POST['school_name'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'country' => trim($_POST['country'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'website' => trim($_POST['website'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'established_year' => !empty($_POST['established_year']) ? intval($_POST['established_year']) : null,
            'school_type' => $_POST['school_type'] ?? 'private',
            'accreditation' => trim($_POST['accreditation'] ?? ''),
            'facilities' => trim($_POST['facilities'] ?? '')
        ];
        
        // 2. Récupérer les données de configuration du thème
        $config_data = [
            'primary_color' => $_POST['primary_color'] ?? '#3498db',
            'secondary_color' => $_POST['secondary_color'] ?? '#2ecc71',
            'accent_color' => $_POST['accent_color'] ?? '#e74c3c',
            'text_color' => $_POST['text_color'] ?? '#2c3e50',
            'background_color' => $_POST['background_color'] ?? '#f8f9fa',
            'currency' => $_POST['currency'] ?? 'FCFA',
            'currency_symbol' => $_POST['currency_symbol'] ?? 'FCFA',
            'tax_rate' => floatval($_POST['tax_rate'] ?? 0),
            'bank_name' => trim($_POST['bank_name'] ?? ''),
            'bank_account' => trim($_POST['bank_account'] ?? ''),
            'payment_instructions' => trim($_POST['payment_instructions'] ?? ''),
            'education_system' => $_POST['education_system'] ?? 'Congolais',
            'academic_calendar' => json_encode([
                'trimestre1' => [
                    'start' => $_POST['term1_start'] ?? $academic_calendar['trimestre1']['start'],
                    'end' => $_POST['term1_end'] ?? $academic_calendar['trimestre1']['end']
                ],
                'trimestre2' => [
                    'start' => $_POST['term2_start'] ?? $academic_calendar['trimestre2']['start'],
                    'end' => $_POST['term2_end'] ?? $academic_calendar['trimestre2']['end']
                ],
                'trimestre3' => [
                    'start' => $_POST['term3_start'] ?? $academic_calendar['trimestre3']['start'],
                    'end' => $_POST['term3_end'] ?? $academic_calendar['trimestre3']['end']
                ]
            ]),
            'grading_system' => json_encode([
                'excellent' => [
                    'min' => floatval($_POST['grade_excellent_min'] ?? 16),
                    'max' => floatval($_POST['grade_excellent_max'] ?? 20),
                    'mention' => 'Excellent'
                ],
                'tres_bien' => [
                    'min' => floatval($_POST['grade_tres_bien_min'] ?? 14),
                    'max' => floatval($_POST['grade_tres_bien_max'] ?? 15.99),
                    'mention' => 'Très Bien'
                ],
                'bien' => [
                    'min' => floatval($_POST['grade_bien_min'] ?? 12),
                    'max' => floatval($_POST['grade_bien_max'] ?? 13.99),
                    'mention' => 'Bien'
                ],
                'assez_bien' => [
                    'min' => floatval($_POST['grade_assez_bien_min'] ?? 10),
                    'max' => floatval($_POST['grade_assez_bien_max'] ?? 11.99),
                    'mention' => 'Assez Bien'
                ],
                'passable' => [
                    'min' => floatval($_POST['grade_passable_min'] ?? 8),
                    'max' => floatval($_POST['grade_passable_max'] ?? 9.99),
                    'mention' => 'Passable'
                ],
                'insuffisant' => [
                    'min' => floatval($_POST['grade_insuffisant_min'] ?? 0),
                    'max' => floatval($_POST['grade_insuffisant_max'] ?? 7.99),
                    'mention' => 'Insuffisant'
                ]
            ]),
            'exam_system' => trim($_POST['exam_system'] ?? ''),
            'welcome_message' => trim($_POST['welcome_message'] ?? ''),
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'social_facebook' => trim($_POST['social_facebook'] ?? ''),
            'social_twitter' => trim($_POST['social_twitter'] ?? ''),
            'social_linkedin' => trim($_POST['social_linkedin'] ?? '')
        ];
        
        try {
            $pdo->beginTransaction();
            
            // 3. Mettre à jour les informations de l'école (NOUVEAU)
            $sql = "UPDATE schools SET ";
            $params = [];
            $updates = [];
            
            foreach ($school_data as $key => $value) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
            
            $sql .= implode(', ', $updates) . " WHERE id = ?";
            $params[] = $school['id'];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // 4. Gestion du logo uploadé
            if (!empty($_FILES['logo']['name'])) {
                $upload_result = uploadSchoolLogo($_FILES['logo'], $school['id']);
                if ($upload_result['success']) {
                    $config_data['logo'] = $upload_result['filename'];
                    
                    // Mettre à jour aussi dans la table schools
                    $stmt = $pdo->prepare("UPDATE schools SET logo = ? WHERE id = ?");
                    $stmt->execute([$config_data['logo'], $school['id']]);
                } else {
                    throw new Exception($upload_result['error']);
                }
            } else {
                $config_data['logo'] = $school_config['logo'];
            }
            
            // 5. Gestion de la bannière uploadée
            if (!empty($_FILES['banner']['name'])) {
                $upload_result = uploadSchoolBanner($_FILES['banner'], $school['id']);
                if ($upload_result['success']) {
                    $config_data['banner'] = $upload_result['filename'];
                } else {
                    throw new Exception($upload_result['error']);
                }
            } else {
                $config_data['banner'] = $school_config['banner'];
            }
            
            // 6. Sauvegarder la configuration
            $result = saveSchoolConfig($school['id'], $config_data);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $pdo->commit();
            
            $message = "Configuration enregistrée avec succès !";
            
            // Recharger les données
            $stmt = $pdo->prepare("SELECT s.*, u.full_name, u.email as user_email FROM schools s JOIN users u ON s.user_id = u.id WHERE s.user_id = ?");
            $stmt->execute([$user_id]);
            $school = $stmt->fetch();
            
            $school_config = getSchoolConfig($school['id']);
            $grading_system = json_decode($school_config['grading_system'] ?? '[]', true);
            $academic_calendar = json_decode($school_config['academic_calendar'] ?? '[]', true);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
        
    } elseif ($action === 'remove_logo') {
        // Supprimer le logo
        if (!empty($school_config['logo'])) {
            $file_path = __DIR__ . '/../../uploads/schools/' . $school_config['logo'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Mettre à jour la configuration et la table schools
            $config_data = $school_config;
            $config_data['logo'] = null;
            saveSchoolConfig($school['id'], $config_data);
            
            $stmt = $pdo->prepare("UPDATE schools SET logo = NULL WHERE id = ?");
            $stmt->execute([$school['id']]);
            
            $message = "Logo supprimé avec succès";
            $school_config = getSchoolConfig($school['id']);
        }
    } elseif ($action === 'remove_banner') {
        // Supprimer la bannière
        if (!empty($school_config['banner'])) {
            $file_path = __DIR__ . '/../../uploads/schools/' . $school_config['banner'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Mettre à jour la configuration
            $config_data = $school_config;
            $config_data['banner'] = null;
            saveSchoolConfig($school['id'], $config_data);
            
            $message = "Bannière supprimée avec succès";
            $school_config = getSchoolConfig($school['id']);
        }
    }
}

// Fonctions d'upload (garder les mêmes que précédemment)
function uploadSchoolLogo($file, $school_id) {
    $uploadDir = __DIR__ . '/../../uploads/schools/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    if (!isset($file['name']) || empty($file['name'])) {
        return ['success' => false, 'error' => 'Aucun fichier sélectionné'];
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'image/webp'];
    $fileType = mime_content_type($file['tmp_name']) ?? $file['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Type de fichier non autorisé'];
    }
    
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Fichier trop volumineux (max 5MB)'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . $school_id . '_' . time() . '.' . $extension;
    $targetFile = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        chmod($targetFile, 0644);
        return [
            'success' => true, 
            'filename' => $filename
        ];
    }
    
    return ['success' => false, 'error' => 'Erreur lors de l\'upload'];
}

function uploadSchoolBanner($file, $school_id) {
    $uploadDir = __DIR__ . '/../../uploads/schools/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    if (!isset($file['name']) || empty($file['name'])) {
        return ['success' => false, 'error' => 'Aucun fichier sélectionné'];
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'image/webp'];
    $fileType = mime_content_type($file['tmp_name']) ?? $file['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Type de fichier non autorisé'];
    }
    
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Fichier trop volumineux (max 10MB)'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'banner_' . $school_id . '_' . time() . '.' . $extension;
    $targetFile = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        chmod($targetFile, 0644);
        return [
            'success' => true, 
            'filename' => $filename
        ];
    }
    
    return ['success' => false, 'error' => 'Erreur lors de l\'upload'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration - <?php echo htmlspecialchars($school['school_name']); ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/admin.css">
    <?php echo applySchoolTheme($school['id']); ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.4.0/css/bootstrap-colorpicker.min.css" rel="stylesheet">
    <style>
        /* Garder tous les styles CSS précédents */
        .config-header {
            background: linear-gradient(135deg, <?php echo $school_config['primary_color']; ?>, <?php echo $school_config['secondary_color']; ?>);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .config-header h1 {
            color: white;
            margin-bottom: 10px;
        }
        
        .config-header p {
            opacity: 0.9;
            margin: 0;
        }
        
        .form-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: #f8f9fa;
            border: none;
            border-radius: 5px 5px 0 0;
            cursor: pointer;
            font-weight: 500;
            color: #6b7280;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-btn:hover {
            background: #e5e7eb;
        }
        
        .tab-btn.active {
            background: var(--primary-color);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .config-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
        }
        
        .config-section h3 {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 4px;
            display: inline-block;
            margin-right: 10px;
            border: 1px solid #ddd;
            cursor: pointer;
        }
        
        .logo-preview, .banner-preview {
            max-width: 100%;
            max-height: 150px;
            margin: 10px 0;
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 5px;
        }
        
        .grading-system-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .grade-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        
        .calendar-inputs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body class="dashboard">
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher...">
            </div>
            <div class="user-info">
                <span><?php echo htmlspecialchars($school['school_name']); ?></span>
                <?php if (!empty($school['logo'])): ?>
                    <img src="<?php echo SITE_URL; ?>uploads/schools/<?php echo htmlspecialchars($school['logo']); ?>" alt="Logo">
                <?php elseif (!empty($school_config['logo'])): ?>
                    <img src="<?php echo SITE_URL; ?>uploads/schools/<?php echo htmlspecialchars($school_config['logo']); ?>" alt="Logo">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #3498db; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
                        <?php echo substr($school['school_name'], 0, 1); ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>
        
        <div class="content">
            <div class="config-header">
                <h1>Configuration de <?php echo htmlspecialchars($school['school_name']); ?></h1>
                <p>Gérez toutes les informations et paramètres de votre établissement</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-tabs">
                <!-- NOUVEAU ONGLET : Informations de l'école -->
                <button type="button" class="tab-btn active" onclick="showTab('school-info')">
                    <i class="fas fa-school"></i> Informations École
                </button>
                <button type="button" class="tab-btn" onclick="showTab('appearance')">
                    <i class="fas fa-paint-brush"></i> Apparence
                </button>
                <button type="button" class="tab-btn" onclick="showTab('branding')">
                    <i class="fas fa-image"></i> Branding
                </button>
                <button type="button" class="tab-btn" onclick="showTab('academic')">
                    <i class="fas fa-graduation-cap"></i> Académique
                </button>
                <button type="button" class="tab-btn" onclick="showTab('financial')">
                    <i class="fas fa-money-bill-wave"></i> Financier
                </button>
                <button type="button" class="tab-btn" onclick="showTab('social')">
                    <i class="fas fa-share-alt"></i> Social
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_config">
                
                <!-- ONGLET 1 : Informations de l'école (NOUVEAU) -->
                <div id="school-info-tab" class="tab-content active">
                    <div class="config-section">
                        <h3><i class="fas fa-info-circle"></i> Informations Générales</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nom de l'École *</label>
                                <input type="text" name="school_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($school['school_name'] ?? ''); ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label>Année de Fondation</label>
                                <input type="number" name="established_year" class="form-control" 
                                       value="<?php echo htmlspecialchars($school['established_year'] ?? ''); ?>" 
                                       min="1900" max="<?php echo date('Y'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Type d'École</label>
                                <select name="school_type" class="form-control">
                                    <option value="private" <?php echo ($school['school_type'] ?? 'private') == 'private' ? 'selected' : ''; ?>>Privée</option>
                                    <option value="public" <?php echo ($school['school_type'] ?? '') == 'public' ? 'selected' : ''; ?>>Publique</option>
                                    <option value="international" <?php echo ($school['school_type'] ?? '') == 'international' ? 'selected' : ''; ?>>Internationale</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Description de l'École</label>
                            <textarea name="description" class="form-control" rows="4" 
                                      placeholder="Décrivez votre établissement..."><?php echo htmlspecialchars($school['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Accréditations</label>
                            <textarea name="accreditation" class="form-control" rows="3" 
                                      placeholder="Liste des accréditations..."><?php echo htmlspecialchars($school['accreditation'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Infrastructures et Équipements</label>
                            <textarea name="facilities" class="form-control" rows="3" 
                                      placeholder="Laboratoires, bibliothèque, terrains de sport..."><?php echo htmlspecialchars($school['facilities'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="config-section">
                        <h3><i class="fas fa-map-marker-alt"></i> Localisation</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Adresse Complète</label>
                                <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($school['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Ville</label>
                                <input type="text" name="city" class="form-control" 
                                       value="<?php echo htmlspecialchars($school['city'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Pays</label>
                                <input type="text" name="country" class="form-control" 
                                       value="<?php echo htmlspecialchars($school['country'] ?? 'Congo'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="config-section">
                        <h3><i class="fas fa-address-book"></i> Contact</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Téléphone</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($school['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($school['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Site Web</label>
                                <input type="url" name="website" class="form-control" 
                                       value="<?php echo htmlspecialchars($school['website'] ?? ''); ?>" 
                                       placeholder="https://">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ONGLET 2 : Apparence (garder votre version) -->
                <div id="appearance-tab" class="tab-content">
                    <div class="config-section">
                        <h3><i class="fas fa-palette"></i> Couleurs du Thème</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Couleur Principale</label>
                                <div class="color-input-group">
                                    <div class="color-preview" id="primaryColorPreview" 
                                         style="background: <?php echo $school_config['primary_color']; ?>;"></div>
                                    <input type="text" name="primary_color" id="primaryColor" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($school_config['primary_color']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Couleur Secondaire</label>
                                <div class="color-input-group">
                                    <div class="color-preview" id="secondaryColorPreview" 
                                         style="background: <?php echo $school_config['secondary_color']; ?>;"></div>
                                    <input type="text" name="secondary_color" id="secondaryColor" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($school_config['secondary_color']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Couleur d'Accent</label>
                                <div class="color-input-group">
                                    <div class="color-preview" id="accentColorPreview" 
                                         style="background: <?php echo $school_config['accent_color']; ?>;"></div>
                                    <input type="text" name="accent_color" id="accentColor" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($school_config['accent_color']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Couleur du Texte</label>
                                <div class="color-input-group">
                                    <div class="color-preview" id="textColorPreview" 
                                         style="background: <?php echo $school_config['text_color']; ?>;"></div>
                                    <input type="text" name="text_color" id="textColor" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($school_config['text_color']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Couleur de Fond</label>
                                <div class="color-input-group">
                                    <div class="color-preview" id="backgroundColorPreview" 
                                         style="background: <?php echo $school_config['background_color']; ?>;"></div>
                                    <input type="text" name="background_color" id="backgroundColor" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($school_config['background_color']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ONGLET 3 : Branding (garder votre version) -->
                <div id="branding-tab" class="tab-content">
                    <div class="config-section">
                        <h3><i class="fas fa-image"></i> Logo et Bannière</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Logo de l'École</label>
                                <?php if (!empty($school['logo']) || !empty($school_config['logo'])): 
                                    $logo = !empty($school['logo']) ? $school['logo'] : $school_config['logo'];
                                ?>
                                    <div>
                                        <img src="<?php echo SITE_URL; ?>uploads/schools/<?php echo htmlspecialchars($logo); ?>" 
                                             alt="Logo actuel" class="logo-preview">
                                        <div style="margin-top: 10px;">
                                            <button type="button" class="btn-small btn-danger" onclick="removeLogo()">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="logo" class="form-control" accept="image/*">
                                <small class="form-text text-muted">Formats: JPG, PNG, GIF, WebP. Max: 5MB</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Bannière</label>
                                <?php if (!empty($school_config['banner'])): ?>
                                    <div>
                                        <img src="<?php echo SITE_URL; ?>uploads/schools/<?php echo htmlspecialchars($school_config['banner']); ?>" 
                                             alt="Bannière actuelle" class="banner-preview">
                                        <div style="margin-top: 10px;">
                                            <button type="button" class="btn-small btn-danger" onclick="removeBanner()">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="banner" class="form-control" accept="image/*">
                                <small class="form-text text-muted">Formats: JPG, PNG, GIF, WebP. Max: 10MB</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Message de Bienvenue</label>
                            <textarea name="welcome_message" class="form-control" rows="3"><?php echo htmlspecialchars($school_config['welcome_message'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Email de Contact</label>
                            <input type="email" name="contact_email" class="form-control" 
                                   value="<?php echo htmlspecialchars($school_config['contact_email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- ONGLET 4 : Académique (avec système de notation amélioré) -->
                <div id="academic-tab" class="tab-content">
                    <div class="config-section">
                        <h3><i class="fas fa-graduation-cap"></i> Système Éducatif</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Système Éducatif</label>
                                <select name="education_system" class="form-control">
                                    <option value="Congolais" <?php echo $school_config['education_system'] == 'Congolais' ? 'selected' : ''; ?>>Congolais</option>
                                    <option value="Français" <?php echo $school_config['education_system'] == 'Français' ? 'selected' : ''; ?>>Français</option>
                                    <option value="Anglais" <?php echo $school_config['education_system'] == 'Anglais' ? 'selected' : ''; ?>>Anglais</option>
                                    <option value="Bilingue" <?php echo $school_config['education_system'] == 'Bilingue' ? 'selected' : ''; ?>>Bilingue</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Système d'Examens</label>
                            <textarea name="exam_system" class="form-control" rows="3"><?php echo htmlspecialchars($school_config['exam_system'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="config-section">
                        <h3><i class="fas fa-star"></i> Système de Notation</h3>
                        
                        <div class="grading-system-grid">
                            <!-- Excellent -->
                            <div class="grade-item">
                                <label>Excellent</label>
                                <div class="form-row" style="margin-top: 10px;">
                                    <div class="form-group">
                                        <input type="number" name="grade_excellent_min" step="0.01" 
                                               class="form-control" placeholder="Min" 
                                               value="<?php echo $grading_system['excellent']['min'] ?? 16; ?>">
                                    </div>
                                    <div class="form-group">
                                        <input type="number" name="grade_excellent_max" step="0.01" 
                                               class="form-control" placeholder="Max" 
                                               value="<?php echo $grading_system['excellent']['max'] ?? 20; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Très Bien -->
                            <div class="grade-item">
                                <label>Très Bien</label>
                                <div class="form-row" style="margin-top: 10px;">
                                    <div class="form-group">
                                        <input type="number" name="grade_tres_bien_min" step="0.01" 
                                               class="form-control" placeholder="Min" 
                                               value="<?php echo $grading_system['tres_bien']['min'] ?? 14; ?>">
                                    </div>
                                    <div class="form-group">
                                        <input type="number" name="grade_tres_bien_max" step="0.01" 
                                               class="form-control" placeholder="Max" 
                                               value="<?php echo $grading_system['tres_bien']['max'] ?? 15.99; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Bien -->
                            <div class="grade-item">
                                <label>Bien</label>
                                <div class="form-row" style="margin-top: 10px;">
                                    <div class="form-group">
                                        <input type="number" name="grade_bien_min" step="0.01" 
                                               class="form-control" placeholder="Min" 
                                               value="<?php echo $grading_system['bien']['min'] ?? 12; ?>">
                                    </div>
                                    <div class="form-group">
                                        <input type="number" name="grade_bien_max" step="0.01" 
                                               class="form-control" placeholder="Max" 
                                               value="<?php echo $grading_system['bien']['max'] ?? 13.99; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Assez Bien -->
                            <div class="grade-item">
                                <label>Assez Bien</label>
                                <div class="form-row" style="margin-top: 10px;">
                                    <div class="form-group">
                                        <input type="number" name="grade_assez_bien_min" step="0.01" 
                                               class="form-control" placeholder="Min" 
                                               value="<?php echo $grading_system['assez_bien']['min'] ?? 10; ?>">
                                    </div>
                                    <div class="form-group">
                                        <input type="number" name="grade_assez_bien_max" step="0.01" 
                                               class="form-control" placeholder="Max" 
                                               value="<?php echo $grading_system['assez_bien']['max'] ?? 11.99; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Passable -->
                            <div class="grade-item">
                                <label>Passable</label>
                                <div class="form-row" style="margin-top: 10px;">
                                    <div class="form-group">
                                        <input type="number" name="grade_passable_min" step="0.01" 
                                               class="form-control" placeholder="Min" 
                                               value="<?php echo $grading_system['passable']['min'] ?? 8; ?>">
                                    </div>
                                    <div class="form-group">
                                        <input type="number" name="grade_passable_max" step="0.01" 
                                               class="form-control" placeholder="Max" 
                                               value="<?php echo $grading_system['passable']['max'] ?? 9.99; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Insuffisant -->
                            <div class="grade-item">
                                <label>Insuffisant</label>
                                <div class="form-row" style="margin-top: 10px;">
                                    <div class="form-group">
                                        <input type="number" name="grade_insuffisant_min" step="0.01" 
                                               class="form-control" placeholder="Min" 
                                               value="<?php echo $grading_system['insuffisant']['min'] ?? 0; ?>">
                                    </div>
                                    <div class="form-group">
                                        <input type="number" name="grade_insuffisant_max" step="0.01" 
                                               class="form-control" placeholder="Max" 
                                               value="<?php echo $grading_system['insuffisant']['max'] ?? 7.99; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="config-section">
                        <h3><i class="fas fa-calendar-alt"></i> Calendrier Académique</h3>
                        
                        <div class="calendar-inputs">
                            <!-- Trimestre 1 -->
                            <div class="grade-item">
                                <label>Trimestre 1</label>
                                <div class="form-group">
                                    <label>Début</label>
                                    <input type="date" name="term1_start" class="form-control" 
                                           value="<?php echo $academic_calendar['trimestre1']['start'] ?? ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Fin</label>
                                    <input type="date" name="term1_end" class="form-control" 
                                           value="<?php echo $academic_calendar['trimestre1']['end'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <!-- Trimestre 2 -->
                            <div class="grade-item">
                                <label>Trimestre 2</label>
                                <div class="form-group">
                                    <label>Début</label>
                                    <input type="date" name="term2_start" class="form-control" 
                                           value="<?php echo $academic_calendar['trimestre2']['start'] ?? ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Fin</label>
                                    <input type="date" name="term2_end" class="form-control" 
                                           value="<?php echo $academic_calendar['trimestre2']['end'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <!-- Trimestre 3 -->
                            <div class="grade-item">
                                <label>Trimestre 3</label>
                                <div class="form-group">
                                    <label>Début</label>
                                    <input type="date" name="term3_start" class="form-control" 
                                           value="<?php echo $academic_calendar['trimestre3']['start'] ?? ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Fin</label>
                                    <input type="date" name="term3_end" class="form-control" 
                                           value="<?php echo $academic_calendar['trimestre3']['end'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ONGLET 5 : Financier -->
                <div id="financial-tab" class="tab-content">
                    <div class="config-section">
                        <h3><i class="fas fa-money-bill-wave"></i> Paramètres Financiers</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Devise</label>
                                <input type="text" name="currency" class="form-control" 
                                       value="<?php echo htmlspecialchars($school_config['currency']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Symbole</label>
                                <input type="text" name="currency_symbol" class="form-control" 
                                       value="<?php echo htmlspecialchars($school_config['currency_symbol']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Taux de Taxe (%)</label>
                                <input type="number" name="tax_rate" step="0.01" min="0" max="100" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($school_config['tax_rate']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nom de la Banque</label>
                                <input type="text" name="bank_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($school_config['bank_name']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Numéro de Compte</label>
                                <input type="text" name="bank_account" class="form-control" 
                                       value="<?php echo htmlspecialchars($school_config['bank_account']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Instructions de Paiement</label>
                            <textarea name="payment_instructions" class="form-control" rows="4"><?php echo htmlspecialchars($school_config['payment_instructions']); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- ONGLET 6 : Social -->
                <div id="social-tab" class="tab-content">
                    <div class="config-section">
                        <h3><i class="fas fa-share-alt"></i> Réseaux Sociaux</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fab fa-facebook" style="color: #1877f2;"></i> Facebook</label>
                                <input type="text" name="social_facebook" class="form-control" 
                                       value="<?php echo htmlspecialchars($school_config['social_facebook']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fab fa-twitter" style="color: #1da1f2;"></i> Twitter/X</label>
                                <input type="text" name="social_twitter" class="form-control" 
                                       value="<?php echo htmlspecialchars($school_config['social_twitter']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fab fa-linkedin" style="color: #0077b5;"></i> LinkedIn</label>
                                <input type="text" name="social_linkedin" class="form-control" 
                                       value="<?php echo htmlspecialchars($school_config['social_linkedin']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Boutons d'action -->
                <div class="form-actions" style="margin-top: 30px; text-align: center;">
                    <button type="submit" class="btn-primary btn-large">
                        <i class="fas fa-save"></i> Enregistrer toutes les modifications
                    </button>
                    <a href="index.php" class="btn-secondary btn-large">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </form>
            
            <!-- Formulaires cachés pour suppression -->
            <form id="removeLogoForm" method="POST" style="display: none;">
                <input type="hidden" name="action" value="remove_logo">
            </form>
            
            <form id="removeBannerForm" method="POST" style="display: none;">
                <input type="hidden" name="action" value="remove_banner">
            </form>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.4.0/js/bootstrap-colorpicker.min.js"></script>
    <script>
        // Gestion des onglets
        function showTab(tabName) {
            // Masquer tous les onglets
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Désactiver tous les boutons d'onglet
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Afficher l'onglet sélectionné
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Activer le bouton correspondant
            event.target.classList.add('active');
        }
        
        // Initialiser les sélecteurs de couleur
        document.addEventListener('DOMContentLoaded', function() {
            // Mettre à jour les aperçus de couleur
            function updateColorPreview(inputId, previewId) {
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);
                
                input.addEventListener('input', function() {
                    preview.style.backgroundColor = this.value;
                    updateHeaderPreview();
                });
            }
            
            // Mettre à jour tous les aperçus
            updateColorPreview('primaryColor', 'primaryColorPreview');
            updateColorPreview('secondaryColor', 'secondaryColorPreview');
            updateColorPreview('accentColor', 'accentColorPreview');
            updateColorPreview('textColor', 'textColorPreview');
            updateColorPreview('backgroundColor', 'backgroundColorPreview');
            
            // Initialiser les color pickers
            $('#primaryColor').colorpicker();
            $('#secondaryColor').colorpicker();
            $('#accentColor').colorpicker();
            $('#textColor').colorpicker();
            $('#backgroundColor').colorpicker();
            
            // Mettre à jour l'en-tête
            function updateHeaderPreview() {
                const primaryColor = document.querySelector('input[name="primary_color"]').value;
                const secondaryColor = document.querySelector('input[name="secondary_color"]').value;
                const header = document.querySelector('.config-header');
                if (header) {
                    header.style.background = `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`;
                }
            }
            
            // Initialiser l'aperçu
            updateHeaderPreview();
        });
        
        // Supprimer le logo
        function removeLogo() {
            if (confirm('Êtes-vous sûr de vouloir supprimer le logo ?')) {
                document.getElementById('removeLogoForm').submit();
            }
        }
        
        // Supprimer la bannière
        function removeBanner() {
            if (confirm('Êtes-vous sûr de vouloir supprimer la bannière ?')) {
                document.getElementById('removeBannerForm').submit();
            }
        }
    </script>
</body>
</html>