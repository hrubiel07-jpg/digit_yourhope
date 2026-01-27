<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/school_config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Location: ../');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer l'ID de l'école
$stmt = $pdo->prepare("SELECT s.*, u.full_name, u.email FROM schools s JOIN users u ON s.user_id = u.id WHERE s.user_id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();
$school_id = $school['id'];

// Récupérer la configuration actuelle
$school_config = getSchoolConfig($school_id);
if (!$school_config) {
    $school_config = getDefaultSchoolConfig();
    $school_config['school_id'] = $school_id;
}

$message = '';
$error = '';

// Traitement du formulaire de configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Thème et couleurs
    $config_data = [
        'school_id' => $school_id,
        'primary_color' => sanitize($_POST['primary_color']),
        'secondary_color' => sanitize($_POST['secondary_color']),
        'accent_color' => sanitize($_POST['accent_color']),
        'text_color' => sanitize($_POST['text_color']),
        'background_color' => sanitize($_POST['background_color']),
        
        // Informations financières
        'currency' => sanitize($_POST['currency']),
        'currency_symbol' => sanitize($_POST['currency_symbol']),
        'tax_rate' => floatval($_POST['tax_rate']),
        'bank_name' => sanitize($_POST['bank_name']),
        'bank_account' => sanitize($_POST['bank_account']),
        'payment_instructions' => sanitize($_POST['payment_instructions']),
        
        // Informations académiques
        'education_system' => sanitize($_POST['education_system']),
        'grading_system' => json_encode([
            'excellent' => [
                'min' => floatval($_POST['grade_excellent_min']),
                'max' => floatval($_POST['grade_excellent_max']),
                'mention' => 'Excellent'
            ],
            'tres_bien' => [
                'min' => floatval($_POST['grade_tres_bien_min']),
                'max' => floatval($_POST['grade_tres_bien_max']),
                'mention' => 'Très Bien'
            ],
            'bien' => [
                'min' => floatval($_POST['grade_bien_min']),
                'max' => floatval($_POST['grade_bien_max']),
                'mention' => 'Bien'
            ],
            'assez_bien' => [
                'min' => floatval($_POST['grade_assez_bien_min']),
                'max' => floatval($_POST['grade_assez_bien_max']),
                'mention' => 'Assez Bien'
            ],
            'passable' => [
                'min' => floatval($_POST['grade_passable_min']),
                'max' => floatval($_POST['grade_passable_max']),
                'mention' => 'Passable'
            ],
            'insuffisant' => [
                'min' => floatval($_POST['grade_insuffisant_min']),
                'max' => floatval($_POST['grade_insuffisant_max']),
                'mention' => 'Insuffisant'
            ]
        ]),
        
        // Calendrier académique
        'academic_calendar' => json_encode([
            'trimestre1' => [
                'start' => $_POST['term1_start'],
                'end' => $_POST['term1_end'],
                'vacations' => json_decode($_POST['term1_vacations'] ?? '[]', true)
            ],
            'trimestre2' => [
                'start' => $_POST['term2_start'],
                'end' => $_POST['term2_end'],
                'vacations' => json_decode($_POST['term2_vacations'] ?? '[]', true)
            ],
            'trimestre3' => [
                'start' => $_POST['term3_start'],
                'end' => $_POST['term3_end'],
                'vacations' => json_decode($_POST['term3_vacations'] ?? '[]', true)
            ]
        ]),
        
        // Informations de contact
        'contact_email' => sanitize($_POST['contact_email']),
        'social_facebook' => sanitize($_POST['social_facebook']),
        'social_twitter' => sanitize($_POST['social_twitter']),
        'social_linkedin' => sanitize($_POST['social_linkedin'])
    ];
    
    // Gestion du logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $upload_result = uploadFile($_FILES['logo'], '../../../uploads/schools/');
        if ($upload_result['success']) {
            $config_data['logo'] = 'schools/' . $upload_result['filename'];
            
            // Mettre à jour aussi dans la table schools
            $stmt = $pdo->prepare("UPDATE schools SET logo = ? WHERE id = ?");
            $stmt->execute([$config_data['logo'], $school_id]);
        } else {
            $error = "Erreur lors du téléchargement du logo: " . $upload_result['error'];
        }
    }
    
    // Gestion de la bannière
    if (isset($_FILES['banner']) && $_FILES['banner']['error'] === 0) {
        $upload_result = uploadFile($_FILES['banner'], '../../../uploads/schools/');
        if ($upload_result['success']) {
            $config_data['banner'] = 'schools/' . $upload_result['filename'];
        } else {
            $error = "Erreur lors du téléchargement de la bannière: " . $upload_result['error'];
        }
    }
    
    // Message de bienvenue
    $config_data['welcome_message'] = sanitize($_POST['welcome_message']);
    
    // Validation
    if (empty($error)) {
        try {
            if (saveSchoolConfig($config_data)) {
                $message = "Configuration enregistrée avec succès";
                
                // Mettre à jour les informations de l'école
                $school_data = [
                    'school_name' => sanitize($_POST['school_name']),
                    'address' => sanitize($_POST['address']),
                    'city' => sanitize($_POST['city']),
                    'country' => sanitize($_POST['country']),
                    'phone' => sanitize($_POST['phone']),
                    'email' => sanitize($_POST['email']),
                    'website' => sanitize($_POST['website']),
                    'description' => sanitize($_POST['description']),
                    'established_year' => intval($_POST['established_year']),
                    'school_type' => sanitize($_POST['school_type']),
                    'accreditation' => sanitize($_POST['accreditation']),
                    'facilities' => sanitize($_POST['facilities'])
                ];
                
                $sql = "UPDATE schools SET ";
                $params = [];
                $updates = [];
                
                foreach ($school_data as $key => $value) {
                    $updates[] = "$key = ?";
                    $params[] = $value;
                }
                
                $sql .= implode(', ', $updates) . " WHERE id = ?";
                $params[] = $school_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                // Rafraîchir les données de l'école
                $stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
                $stmt->execute([$school_id]);
                $school = $stmt->fetch();
                
                // Rafraîchir la configuration
                $school_config = getSchoolConfig($school_id);
            } else {
                $error = "Erreur lors de l'enregistrement de la configuration";
            }
            
        } catch (Exception $e) {
            $error = "Erreur: " . $e->getMessage();
        }
    }
}

// Décode les systèmes de notation et calendrier
$grading_system = json_decode($school_config['grading_system'] ?? '[]', true);
$academic_calendar = json_decode($school_config['academic_calendar'] ?? '[]', true);

// Si vide, utiliser les valeurs par défaut
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
            'vacations' => [
                ['start' => $current_year . '-10-28', 'end' => $current_year . '-11-03', 'label' => 'Toussaint'],
                ['start' => $current_year . '-12-21', 'end' => ($current_year + 1) . '-01-05', 'label' => 'Noël']
            ]
        ],
        'trimestre2' => [
            'start' => ($current_year + 1) . '-01-06',
            'end' => ($current_year + 1) . '-03-28',
            'vacations' => [
                ['start' => ($current_year + 1) . '-02-17', 'end' => ($current_year + 1) . '-02-23', 'label' => 'Carnaval']
            ]
        ],
        'trimestre3' => [
            'start' => ($current_year + 1) . '-03-31',
            'end' => ($current_year + 1) . '-06-30',
            'vacations' => [
                ['start' => ($current_year + 1) . '-04-14', 'end' => ($current_year + 1) . '-04-20', 'label' => 'Pâques']
            ]
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration - <?php echo htmlspecialchars($school['school_name']); ?></title>
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    <?php echo applySchoolTheme($school_id); ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.4.0/css/bootstrap-colorpicker.min.css" rel="stylesheet">
    <style>
        .config-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .config-header h1 {
            color: white;
            margin-bottom: 10px;
        }
        
        .config-header p {
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .config-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 5px;
            flex-wrap: wrap;
        }
        
        .config-tab {
            padding: 12px 25px;
            background: none;
            border: none;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .config-tab:hover {
            background: #f8f9fa;
            color: var(--primary-color);
        }
        
        .config-tab.active {
            background: white;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            margin-bottom: -2px;
        }
        
        .config-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            display: none;
        }
        
        .config-section.active {
            display: block;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .section-header h3 {
            margin: 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .color-preview {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .color-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .color-display {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            border: 3px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        
        .color-label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }
        
        .color-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .color-input {
            width: 100px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: monospace;
        }
        
        .color-picker-btn {
            width: 40px;
            height: 40px;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .grading-system {
            margin: 20px 0;
        }
        
        .grade-range {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .grade-label {
            width: 150px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .range-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
            flex: 1;
        }
        
        .range-input {
            width: 80px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
        }
        
        .grade-bar {
            flex: 2;
            height: 20px;
            background: linear-gradient(to right, 
                #e74c3c 0%, 
                #e74c3c <?php echo ($grading_system['insuffisant']['max'] / 20 * 100); ?>%,
                #d35400 <?php echo ($grading_system['insuffisant']['max'] / 20 * 100); ?>%,
                #d35400 <?php echo ($grading_system['passable']['max'] / 20 * 100); ?>%,
                #e67e22 <?php echo ($grading_system['passable']['max'] / 20 * 100); ?>%,
                #e67e22 <?php echo ($grading_system['assez_bien']['max'] / 20 * 100); ?>%,
                #f39c12 <?php echo ($grading_system['assez_bien']['max'] / 20 * 100); ?>%,
                #f39c12 <?php echo ($grading_system['bien']['max'] / 20 * 100); ?>%,
                #2ecc71 <?php echo ($grading_system['bien']['max'] / 20 * 100); ?>%,
                #2ecc71 <?php echo ($grading_system['tres_bien']['max'] / 20 * 100); ?>%,
                #27ae60 <?php echo ($grading_system['tres_bien']['max'] / 20 * 100); ?>%,
                #27ae60 100%);
            border-radius: 10px;
            position: relative;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .term-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #eee;
        }
        
        .term-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .term-title {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .term-dates {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .vacation-list {
            margin-top: 15px;
        }
        
        .vacation-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .vacation-dates {
            font-size: 0.9rem;
            color: #666;
        }
        
        .vacation-label {
            font-weight: 500;
        }
        
        .logo-preview {
            display: flex;
            gap: 30px;
            align-items: center;
            margin: 20px 0;
        }
        
        .current-logo {
            width: 150px;
            height: 150px;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
        
        .current-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .file-upload {
            flex: 1;
        }
        
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-area:hover {
            border-color: var(--primary-color);
            background: #f8f9fa;
        }
        
        .upload-area i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 15px;
        }
        
        .upload-info {
            color: #666;
            font-size: 0.9rem;
        }
        
        .preview-banner {
            width: 100%;
            height: 200px;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
            border: 2px solid #eee;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .preview-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .system-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-label {
            color: #666;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .form-actions {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .preview-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin: 20px 0;
            border: 1px solid #eee;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .preview-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .preview-buttons {
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }
        
        .preview-btn {
            padding: 10px 20px;
            border-radius: 5px;
            border: 1px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .preview-btn:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .config-sidebar {
            position: sticky;
            top: 20px;
        }
        
        .config-nav {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #eee;
        }
        
        .config-nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: #666;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        
        .config-nav-item:hover {
            background: #f8f9fa;
            color: var(--primary-color);
        }
        
        .config-nav-item.active {
            background: var(--primary-color);
            color: white;
        }
        
        .config-nav-item i {
            width: 20px;
            text-align: center;
        }
    </style>
</head>
<body class="dashboard">
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher dans la configuration..." id="globalSearch">
            </div>
            <div class="user-info">
                <span>Configuration</span>
                <?php if ($school['logo']): ?>
                    <img src="../../../uploads/<?php echo htmlspecialchars($school['logo']); ?>" alt="Logo">
                <?php else: ?>
                    <img src="../../../assets/images/default-school.png" alt="Logo">
                <?php endif; ?>
            </div>
        </header>
        
        <div class="content">
            <div class="config-header">
                <h1><i class="fas fa-cog"></i> Configuration de l'École</h1>
                <p>Personnalisez l'apparence, les paramètres académiques et les informations de votre établissement</p>
            </div>
            
            <?php if ($message || $error): ?>
                <div class="alert <?php echo $error ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo $error ? $error : $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="config-tabs">
                <button class="config-tab active" data-tab="general">
                    <i class="fas fa-info-circle"></i> Informations Générales
                </button>
                <button class="config-tab" data-tab="theme">
                    <i class="fas fa-palette"></i> Thème & Apparence
                </button>
                <button class="config-tab" data-tab="academic">
                    <i class="fas fa-graduation-cap"></i> Paramètres Académiques
                </button>
                <button class="config-tab" data-tab="financial">
                    <i class="fas fa-money-bill-wave"></i> Paramètres Financiers
                </button>
                <button class="config-tab" data-tab="media">
                    <i class="fas fa-images"></i> Médias & Logos
                </button>
                <button class="config-tab" data-tab="social">
                    <i class="fas fa-share-alt"></i> Réseaux Sociaux
                </button>
                <button class="config-tab" data-tab="system">
                    <i class="fas fa-server"></i> Système
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="configForm">
                
                <!-- Section: Informations Générales -->
                <div id="general" class="config-section active">
                    <div class="section-header">
                        <h3><i class="fas fa-info-circle"></i> Informations Générales de l'École</h3>
                        <span class="badge badge-primary">Obligatoire</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="school_name">Nom de l'école *</label>
                            <input type="text" id="school_name" name="school_name" 
                                   value="<?php echo htmlspecialchars($school['school_name']); ?>" 
                                   required class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="school_type">Type d'établissement</label>
                            <select id="school_type" name="school_type" class="form-control">
                                <option value="private" <?php echo ($school['school_type'] ?? 'private') == 'private' ? 'selected' : ''; ?>>Privé</option>
                                <option value="public" <?php echo ($school['school_type'] ?? '') == 'public' ? 'selected' : ''; ?>>Public</option>
                                <option value="international" <?php echo ($school['school_type'] ?? '') == 'international' ? 'selected' : ''; ?>>International</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="established_year">Année de création</label>
                            <input type="number" id="established_year" name="established_year" 
                                   value="<?php echo htmlspecialchars($school['established_year'] ?? ''); ?>" 
                                   class="form-control" min="1800" max="<?php echo date('Y'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="accreditation">Accréditation</label>
                            <input type="text" id="accreditation" name="accreditation" 
                                   value="<?php echo htmlspecialchars($school['accreditation'] ?? ''); ?>" 
                                   class="form-control" placeholder="Ex: Ministère de l'Éducation">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description de l'école</label>
                        <textarea id="description" name="description" rows="4" class="form-control"><?php echo htmlspecialchars($school['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="facilities">Installations & Équipements</label>
                        <textarea id="facilities" name="facilities" rows="3" class="form-control" 
                                  placeholder="Séparer par des virgules"><?php echo htmlspecialchars($school['facilities'] ?? ''); ?></textarea>
                        <small style="color: #666;">Ex: Bibliothèque, Laboratoire informatique, Terrain de sport, Cantines</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="address">Adresse</label>
                            <textarea id="address" name="address" rows="2" class="form-control"><?php echo htmlspecialchars($school['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">Ville</label>
                            <input type="text" id="city" name="city" 
                                   value="<?php echo htmlspecialchars($school['city'] ?? ''); ?>" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="country">Pays</label>
                            <input type="text" id="country" name="country" 
                                   value="<?php echo htmlspecialchars($school['country'] ?? 'Congo'); ?>" 
                                   class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Téléphone</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($school['phone'] ?? ''); ?>" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email de contact</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($school['email'] ?? ''); ?>" 
                                   class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="website">Site web</label>
                        <input type="url" id="website" name="website" 
                               value="<?php echo htmlspecialchars($school['website'] ?? ''); ?>" 
                               class="form-control" placeholder="https://">
                    </div>
                </div>
                
                <!-- Section: Thème & Apparence -->
                <div id="theme" class="config-section">
                    <div class="section-header">
                        <h3><i class="fas fa-palette"></i> Thème & Couleurs</h3>
                        <span class="badge badge-primary">Personnalisation</span>
                    </div>
                    
                    <div class="color-preview">
                        <div class="color-item">
                            <div class="color-display" id="primaryColorDisplay" 
                                 style="background-color: <?php echo $school_config['primary_color']; ?>"></div>
                            <span class="color-label">Couleur principale</span>
                            <div class="color-input-group">
                                <input type="text" id="primary_color" name="primary_color" 
                                       value="<?php echo $school_config['primary_color']; ?>" 
                                       class="color-input">
                                <div class="color-picker-btn" id="primaryColorPicker">
                                    <i class="fas fa-eye-dropper"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="color-item">
                            <div class="color-display" id="secondaryColorDisplay" 
                                 style="background-color: <?php echo $school_config['secondary_color']; ?>"></div>
                            <span class="color-label">Couleur secondaire</span>
                            <div class="color-input-group">
                                <input type="text" id="secondary_color" name="secondary_color" 
                                       value="<?php echo $school_config['secondary_color']; ?>" 
                                       class="color-input">
                                <div class="color-picker-btn" id="secondaryColorPicker">
                                    <i class="fas fa-eye-dropper"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="color-item">
                            <div class="color-display" id="accentColorDisplay" 
                                 style="background-color: <?php echo $school_config['accent_color']; ?>"></div>
                            <span class="color-label">Couleur d'accent</span>
                            <div class="color-input-group">
                                <input type="text" id="accent_color" name="accent_color" 
                                       value="<?php echo $school_config['accent_color']; ?>" 
                                       class="color-input">
                                <div class="color-picker-btn" id="accentColorPicker">
                                    <i class="fas fa-eye-dropper"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="color-item">
                            <div class="color-display" id="textColorDisplay" 
                                 style="background-color: <?php echo $school_config['text_color']; ?>"></div>
                            <span class="color-label">Couleur du texte</span>
                            <div class="color-input-group">
                                <input type="text" id="text_color" name="text_color" 
                                       value="<?php echo $school_config['text_color']; ?>" 
                                       class="color-input">
                                <div class="color-picker-btn" id="textColorPicker">
                                    <i class="fas fa-eye-dropper"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="color-item">
                            <div class="color-display" id="backgroundColorDisplay" 
                                 style="background-color: <?php echo $school_config['background_color']; ?>"></div>
                            <span class="color-label">Fond</span>
                            <div class="color-input-group">
                                <input type="text" id="background_color" name="background_color" 
                                       value="<?php echo $school_config['background_color']; ?>" 
                                       class="color-input">
                                <div class="color-picker-btn" id="backgroundColorPicker">
                                    <i class="fas fa-eye-dropper"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="preview-container">
                        <div class="preview-header">
                            <h4 style="color: white; margin: 0;">Aperçu du thème</h4>
                        </div>
                        
                        <div class="preview-buttons">
                            <button type="button" class="preview-btn" style="background: <?php echo $school_config['primary_color']; ?>; color: white;">
                                Bouton principal
                            </button>
                            <button type="button" class="preview-btn" style="background: <?php echo $school_config['secondary_color']; ?>; color: white;">
                                Bouton secondaire
                            </button>
                            <button type="button" class="preview-btn" style="border-color: <?php echo $school_config['accent_color']; ?>; color: <?php echo $school_config['accent_color']; ?>;">
                                Bouton accent
                            </button>
                        </div>
                        
                        <div style="color: <?php echo $school_config['text_color']; ?>; padding: 20px; background: <?php echo $school_config['background_color']; ?>; border-radius: 8px; margin-top: 20px;">
                            <h4 style="color: <?php echo $school_config['primary_color']; ?>;">Exemple de texte</h4>
                            <p>Ceci est un exemple de texte avec les couleurs configurées. Le fond utilise la couleur d'arrière-plan définie.</p>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label for="welcome_message">Message de bienvenue</label>
                        <textarea id="welcome_message" name="welcome_message" rows="3" class="form-control"><?php echo htmlspecialchars($school_config['welcome_message'] ?? ''); ?></textarea>
                        <small style="color: #666;">Ce message s'affichera sur le tableau de bord de l'école</small>
                    </div>
                </div>
                
                <!-- Section: Paramètres Académiques -->
                <div id="academic" class="config-section">
                    <div class="section-header">
                        <h3><i class="fas fa-graduation-cap"></i> Paramètres Académiques</h3>
                        <span class="badge badge-primary">Configuration</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="education_system">Système éducatif</label>
                            <select id="education_system" name="education_system" class="form-control">
                                <option value="Congolais" <?php echo ($school_config['education_system'] ?? 'Congolais') == 'Congolais' ? 'selected' : ''; ?>>Congolais</option>
                                <option value="Français" <?php echo ($school_config['education_system'] ?? '') == 'Français' ? 'selected' : ''; ?>>Français</option>
                                <option value="Anglais" <?php echo ($school_config['education_system'] ?? '') == 'Anglais' ? 'selected' : ''; ?>>Anglais</option>
                                <option value="Bilingue" <?php echo ($school_config['education_system'] ?? '') == 'Bilingue' ? 'selected' : ''; ?>>Bilingue</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grading-system">
                        <h4 style="margin-bottom: 20px;">Système de notation</h4>
                        
                        <div class="grade-range">
                            <div class="grade-label">Excellent</div>
                            <div class="range-inputs">
                                <input type="number" name="grade_excellent_min" class="range-input" 
                                       value="<?php echo $grading_system['excellent']['min']; ?>" step="0.01" min="0" max="20">
                                <span>à</span>
                                <input type="number" name="grade_excellent_max" class="range-input" 
                                       value="<?php echo $grading_system['excellent']['max']; ?>" step="0.01" min="0" max="20">
                                <span>/20</span>
                            </div>
                            <div class="grade-bar"></div>
                        </div>
                        
                        <div class="grade-range">
                            <div class="grade-label">Très Bien</div>
                            <div class="range-inputs">
                                <input type="number" name="grade_tres_bien_min" class="range-input" 
                                       value="<?php echo $grading_system['tres_bien']['min']; ?>" step="0.01" min="0" max="20">
                                <span>à</span>
                                <input type="number" name="grade_tres_bien_max" class="range-input" 
                                       value="<?php echo $grading_system['tres_bien']['max']; ?>" step="0.01" min="0" max="20">
                                <span>/20</span>
                            </div>
                            <div class="grade-bar"></div>
                        </div>
                        
                        <div class="grade-range">
                            <div class="grade-label">Bien</div>
                            <div class="range-inputs">
                                <input type="number" name="grade_bien_min" class="range-input" 
                                       value="<?php echo $grading_system['bien']['min']; ?>" step="0.01" min="0" max="20">
                                <span>à</span>
                                <input type="number" name="grade_bien_max" class="range-input" 
                                       value="<?php echo $grading_system['bien']['max']; ?>" step="0.01" min="0" max="20">
                                <span>/20</span>
                            </div>
                            <div class="grade-bar"></div>
                        </div>
                        
                        <div class="grade-range">
                            <div class="grade-label">Assez Bien</div>
                            <div class="range-inputs">
                                <input type="number" name="grade_assez_bien_min" class="range-input" 
                                       value="<?php echo $grading_system['assez_bien']['min']; ?>" step="0.01" min="0" max="20">
                                <span>à</span>
                                <input type="number" name="grade_assez_bien_max" class="range-input" 
                                       value="<?php echo $grading_system['assez_bien']['max']; ?>" step="0.01" min="0" max="20">
                                <span>/20</span>
                            </div>
                            <div class="grade-bar"></div>
                        </div>
                        
                        <div class="grade-range">
                            <div class="grade-label">Passable</div>
                            <div class="range-inputs">
                                <input type="number" name="grade_passable_min" class="range-input" 
                                       value="<?php echo $grading_system['passable']['min']; ?>" step="0.01" min="0" max="20">
                                <span>à</span>
                                <input type="number" name="grade_passable_max" class="range-input" 
                                       value="<?php echo $grading_system['passable']['max']; ?>" step="0.01" min="0" max="20">
                                <span>/20</span>
                            </div>
                            <div class="grade-bar"></div>
                        </div>
                        
                        <div class="grade-range">
                            <div class="grade-label">Insuffisant</div>
                            <div class="range-inputs">
                                <input type="number" name="grade_insuffisant_min" class="range-input" 
                                       value="<?php echo $grading_system['insuffisant']['min']; ?>" step="0.01" min="0" max="20">
                                <span>à</span>
                                <input type="number" name="grade_insuffisant_max" class="range-input" 
                                       value="<?php echo $grading_system['insuffisant']['max']; ?>" step="0.01" min="0" max="20">
                                <span>/20</span>
                            </div>
                            <div class="grade-bar"></div>
                        </div>
                    </div>
                    
                    <div class="calendar-grid">
                        <div class="term-card">
                            <div class="term-header">
                                <span class="term-title">Trimestre 1</span>
                            </div>
                            <div class="term-dates">
                                <div class="form-group">
                                    <label>Date début</label>
                                    <input type="date" name="term1_start" 
                                           value="<?php echo $academic_calendar['trimestre1']['start']; ?>" 
                                           class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Date fin</label>
                                    <input type="date" name="term1_end" 
                                           value="<?php echo $academic_calendar['trimestre1']['end']; ?>" 
                                           class="form-control">
                                </div>
                            </div>
                            <div class="vacation-list">
                                <h5 style="margin-bottom: 10px;">Vacances</h5>
                                <div id="term1_vacations_list">
                                    <!-- Les vacances seront ajoutées dynamiquement -->
                                </div>
                                <button type="button" class="btn-secondary" onclick="addVacation('term1')" style="margin-top: 10px;">
                                    <i class="fas fa-plus"></i> Ajouter une vacance
                                </button>
                                <input type="hidden" name="term1_vacations" id="term1_vacations">
                            </div>
                        </div>
                        
                        <div class="term-card">
                            <div class="term-header">
                                <span class="term-title">Trimestre 2</span>
                            </div>
                            <div class="term-dates">
                                <div class="form-group">
                                    <label>Date début</label>
                                    <input type="date" name="term2_start" 
                                           value="<?php echo $academic_calendar['trimestre2']['start']; ?>" 
                                           class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Date fin</label>
                                    <input type="date" name="term2_end" 
                                           value="<?php echo $academic_calendar['trimestre2']['end']; ?>" 
                                           class="form-control">
                                </div>
                            </div>
                            <div class="vacation-list">
                                <h5 style="margin-bottom: 10px;">Vacances</h5>
                                <div id="term2_vacations_list"></div>
                                <button type="button" class="btn-secondary" onclick="addVacation('term2')" style="margin-top: 10px;">
                                    <i class="fas fa-plus"></i> Ajouter une vacance
                                </button>
                                <input type="hidden" name="term2_vacations" id="term2_vacations">
                            </div>
                        </div>
                        
                        <div class="term-card">
                            <div class="term-header">
                                <span class="term-title">Trimestre 3</span>
                            </div>
                            <div class="term-dates">
                                <div class="form-group">
                                    <label>Date début</label>
                                    <input type="date" name="term3_start" 
                                           value="<?php echo $academic_calendar['trimestre3']['start']; ?>" 
                                           class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Date fin</label>
                                    <input type="date" name="term3_end" 
                                           value="<?php echo $academic_calendar['trimestre3']['end']; ?>" 
                                           class="form-control">
                                </div>
                            </div>
                            <div class="vacation-list">
                                <h5 style="margin-bottom: 10px;">Vacances</h5>
                                <div id="term3_vacations_list"></div>
                                <button type="button" class="btn-secondary" onclick="addVacation('term3')" style="margin-top: 10px;">
                                    <i class="fas fa-plus"></i> Ajouter une vacance
                                </button>
                                <input type="hidden" name="term3_vacations" id="term3_vacations">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Section: Paramètres Financiers -->
                <div id="financial" class="config-section">
                    <div class="section-header">
                        <h3><i class="fas fa-money-bill-wave"></i> Paramètres Financiers</h3>
                        <span class="badge badge-primary">Monnaie & Taxes</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="currency">Devise</label>
                            <select id="currency" name="currency" class="form-control">
                                <option value="FCFA" <?php echo ($school_config['currency'] ?? 'FCFA') == 'FCFA' ? 'selected' : ''; ?>>Franc CFA (FCFA)</option>
                                <option value="USD" <?php echo ($school_config['currency'] ?? '') == 'USD' ? 'selected' : ''; ?>>Dollar US ($)</option>
                                <option value="EUR" <?php echo ($school_config['currency'] ?? '') == 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                <option value="XAF" <?php echo ($school_config['currency'] ?? '') == 'XAF' ? 'selected' : ''; ?>>Franc CFA (XAF)</option>
                                <option value="CDF" <?php echo ($school_config['currency'] ?? '') == 'CDF' ? 'selected' : ''; ?>>Franc Congolais (FC)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="currency_symbol">Symbole monétaire</label>
                            <input type="text" id="currency_symbol" name="currency_symbol" 
                                   value="<?php echo $school_config['currency_symbol'] ?? 'FCFA'; ?>" 
                                   class="form-control" maxlength="10">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tax_rate">Taux de taxe (%)</label>
                            <input type="number" id="tax_rate" name="tax_rate" 
                                   value="<?php echo $school_config['tax_rate'] ?? 0; ?>" 
                                   class="form-control" step="0.01" min="0" max="100">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bank_name">Nom de la banque</label>
                            <input type="text" id="bank_name" name="bank_name" 
                                   value="<?php echo htmlspecialchars($school_config['bank_name'] ?? ''); ?>" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="bank_account">Numéro de compte</label>
                            <input type="text" id="bank_account" name="bank_account" 
                                   value="<?php echo htmlspecialchars($school_config['bank_account'] ?? ''); ?>" 
                                   class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_instructions">Instructions de paiement</label>
                        <textarea id="payment_instructions" name="payment_instructions" rows="4" class="form-control"><?php echo htmlspecialchars($school_config['payment_instructions'] ?? ''); ?></textarea>
                        <small style="color: #666;">Instructions pour les paiements (virement, chèque, etc.)</small>
                    </div>
                </div>
                
                <!-- Section: Médias & Logos -->
                <div id="media" class="config-section">
                    <div class="section-header">
                        <h3><i class="fas fa-images"></i> Logo & Bannière</h3>
                        <span class="badge badge-primary">Image</span>
                    </div>
                    
                    <div class="logo-preview">
                        <div class="current-logo">
                            <?php if ($school['logo']): ?>
                                <img src="../../../uploads/<?php echo htmlspecialchars($school['logo']); ?>" 
                                     alt="Logo actuel">
                            <?php else: ?>
                                <i class="fas fa-school fa-3x" style="color: #ccc;"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="file-upload">
                            <label for="logo" class="upload-area">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div style="margin-bottom: 10px;">
                                    <strong>Cliquez pour télécharger un nouveau logo</strong>
                                </div>
                                <div class="upload-info">
                                    PNG, JPG ou SVG • Max 2MB
                                </div>
                            </label>
                            <input type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.svg" 
                                   style="display: none;" onchange="previewLogo(this)">
                            <div id="logoPreview" style="margin-top: 10px; display: none;">
                                <img id="logoPreviewImg" style="max-width: 200px; max-height: 150px; border-radius: 5px;">
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin: 30px 0;">
                        <label for="banner" style="display: block; margin-bottom: 10px; font-weight: 500;">
                            <i class="fas fa-image"></i> Bannière du site
                        </label>
                        
                        <div class="preview-banner" id="bannerPreviewContainer">
                            <?php if ($school_config['banner']): ?>
                                <img src="../../../uploads/<?php echo htmlspecialchars($school_config['banner']); ?>" 
                                     alt="Bannière actuelle">
                            <?php else: ?>
                                <div style="text-align: center; color: #999;">
                                    <i class="fas fa-image fa-3x" style="margin-bottom: 10px;"></i>
                                    <div>Aucune bannière configurée</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <label for="banner" class="btn-secondary" style="display: inline-block; padding: 10px 20px; cursor: pointer;">
                                <i class="fas fa-upload"></i> Choisir une bannière
                            </label>
                            <input type="file" id="banner" name="banner" accept=".png,.jpg,.jpeg" 
                                   style="display: none;" onchange="previewBanner(this)">
                        </div>
                    </div>
                </div>
                
                <!-- Section: Réseaux Sociaux -->
                <div id="social" class="config-section">
                    <div class="section-header">
                        <h3><i class="fas fa-share-alt"></i> Réseaux Sociaux</h3>
                        <span class="badge badge-primary">Communication</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_email">Email de contact</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" id="contact_email" name="contact_email" 
                                       value="<?php echo htmlspecialchars($school_config['contact_email'] ?? ''); ?>" 
                                       class="form-control" placeholder="contact@ecole.com">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="social_facebook">Facebook</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fab fa-facebook-f"></i>
                                </span>
                                <input type="url" id="social_facebook" name="social_facebook" 
                                       value="<?php echo htmlspecialchars($school_config['social_facebook'] ?? ''); ?>" 
                                       class="form-control" placeholder="https://facebook.com/ecole">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="social_twitter">Twitter</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fab fa-twitter"></i>
                                </span>
                                <input type="url" id="social_twitter" name="social_twitter" 
                                       value="<?php echo htmlspecialchars($school_config['social_twitter'] ?? ''); ?>" 
                                       class="form-control" placeholder="https://twitter.com/ecole">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="social_linkedin">LinkedIn</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fab fa-linkedin-in"></i>
                                </span>
                                <input type="url" id="social_linkedin" name="social_linkedin" 
                                       value="<?php echo htmlspecialchars($school_config['social_linkedin'] ?? ''); ?>" 
                                       class="form-control" placeholder="https://linkedin.com/school/ecole">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Section: Système -->
                <div id="system" class="config-section">
                    <div class="section-header">
                        <h3><i class="fas fa-server"></i> Informations Système</h3>
                        <span class="badge badge-primary">Technique</span>
                    </div>
                    
                    <div class="system-info">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">ID de l'école:</span>
                                <span class="info-value"><?php echo $school_id; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Nom du domaine:</span>
                                <span class="info-value"><?php echo $_SERVER['HTTP_HOST']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Version PHP:</span>
                                <span class="info-value"><?php echo phpversion(); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Version MySQL:</span>
                                <span class="info-value">
                                    <?php 
                                    $stmt = $pdo->query("SELECT VERSION() as version");
                                    echo $stmt->fetch()['version'];
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Dernière mise à jour:</span>
                                <span class="info-value"><?php echo date('d/m/Y H:i'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Statut:</span>
                                <span class="info-value" style="color: #27ae60;">
                                    <i class="fas fa-check-circle"></i> Actif
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <h4 style="margin-bottom: 15px;">Actions système</h4>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button type="button" class="btn-secondary" onclick="clearCache()">
                                <i class="fas fa-broom"></i> Vider le cache
                            </button>
                            <button type="button" class="btn-secondary" onclick="exportData()">
                                <i class="fas fa-download"></i> Exporter les données
                            </button>
                            <button type="button" class="btn-secondary" onclick="testEmail()">
                                <i class="fas fa-envelope"></i> Tester les emails
                            </button>
                            <a href="backup.php" class="btn-secondary">
                                <i class="fas fa-save"></i> Sauvegarde
                            </a>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px;">Statistiques de l'école</h4>
                        <?php
                        // Récupérer les statistiques
                        $stats = getSchoolStatistics($school_id);
                        ?>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Élèves inscrits:</span>
                                <span class="info-value"><?php echo $stats['total_students'] ?? 0; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Enseignants:</span>
                                <span class="info-value"><?php echo $stats['total_teachers'] ?? 0; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Classes actives:</span>
                                <span class="info-value"><?php echo $stats['total_classes'] ?? 0; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Recettes totales:</span>
                                <span class="info-value"><?php echo number_format($stats['total_paid'] ?? 0, 0, ',', ' '); ?> <?php echo $school_config['currency_symbol']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Enregistrer toutes les modifications
                    </button>
                    <button type="button" class="btn-secondary" onclick="resetToDefault()">
                        <i class="fas fa-redo"></i> Restaurer les valeurs par défaut
                    </button>
                    <a href="dashboard.php" class="btn-secondary">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../../../assets/js/dashboard.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.4.0/js/bootstrap-colorpicker.min.js"></script>
    <script>
        // Gestion des onglets
        document.querySelectorAll('.config-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Désactiver tous les onglets
                document.querySelectorAll('.config-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.config-section').forEach(s => s.classList.remove('active'));
                
                // Activer l'onglet cliqué
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
                
                // Sauvegarder l'onglet actif dans le localStorage
                localStorage.setItem('activeConfigTab', tabId);
            });
        });
        
        // Restaurer l'onglet actif
        const savedTab = localStorage.getItem('activeConfigTab') || 'general';
        document.querySelector(`[data-tab="${savedTab}"]`).click();
        
        // Initialiser les sélecteurs de couleur
        $('#primaryColorPicker').colorpicker({
            color: '<?php echo $school_config['primary_color']; ?>',
            format: 'hex'
        }).on('changeColor', function(e) {
            document.getElementById('primary_color').value = e.color.toHex();
            document.getElementById('primaryColorDisplay').style.backgroundColor = e.color.toHex();
            updatePreview();
        });
        
        $('#secondaryColorPicker').colorpicker({
            color: '<?php echo $school_config['secondary_color']; ?>',
            format: 'hex'
        }).on('changeColor', function(e) {
            document.getElementById('secondary_color').value = e.color.toHex();
            document.getElementById('secondaryColorDisplay').style.backgroundColor = e.color.toHex();
            updatePreview();
        });
        
        $('#accentColorPicker').colorpicker({
            color: '<?php echo $school_config['accent_color']; ?>',
            format: 'hex'
        }).on('changeColor', function(e) {
            document.getElementById('accent_color').value = e.color.toHex();
            document.getElementById('accentColorDisplay').style.backgroundColor = e.color.toHex();
            updatePreview();
        });
        
        $('#textColorPicker').colorpicker({
            color: '<?php echo $school_config['text_color']; ?>',
            format: 'hex'
        }).on('changeColor', function(e) {
            document.getElementById('text_color').value = e.color.toHex();
            document.getElementById('textColorDisplay').style.backgroundColor = e.color.toHex();
            updatePreview();
        });
        
        $('#backgroundColorPicker').colorpicker({
            color: '<?php echo $school_config['background_color']; ?>',
            format: 'hex'
        }).on('changeColor', function(e) {
            document.getElementById('background_color').value = e.color.toHex();
            document.getElementById('backgroundColorDisplay').style.backgroundColor = e.color.toHex();
            updatePreview();
        });
        
        // Mettre à jour l'aperçu en temps réel
        function updatePreview() {
            const primaryColor = document.getElementById('primary_color').value;
            const secondaryColor = document.getElementById('secondary_color').value;
            const accentColor = document.getElementById('accent_color').value;
            const textColor = document.getElementById('text_color').value;
            const backgroundColor = document.getElementById('background_color').value;
            
            // Mettre à jour l'aperçu du thème
            const previewButtons = document.querySelectorAll('.preview-btn');
            previewButtons[0].style.backgroundColor = primaryColor;
            previewButtons[1].style.backgroundColor = secondaryColor;
            previewButtons[2].style.borderColor = accentColor;
            previewButtons[2].style.color = accentColor;
            
            // Mettre à jour le texte d'exemple
            const exampleText = document.querySelector('.config-section#theme .preview-container > div:last-child');
            exampleText.style.color = textColor;
            exampleText.style.backgroundColor = backgroundColor;
            exampleText.querySelector('h4').style.color = primaryColor;
        }
        
        // Mettre à jour les affichages de couleur
        function updateColorDisplays() {
            const colors = ['primary', 'secondary', 'accent', 'text', 'background'];
            colors.forEach(color => {
                const input = document.getElementById(`${color}_color`);
                const display = document.getElementById(`${color}ColorDisplay`);
                if (input && display) {
                    display.style.backgroundColor = input.value;
                }
            });
            updatePreview();
        }
        
        // Écouter les changements manuels dans les champs de couleur
        document.querySelectorAll('.color-input').forEach(input => {
            input.addEventListener('input', updateColorDisplays);
        });
        
        // Prévisualisation du logo
        function previewLogo(input) {
            const preview = document.getElementById('logoPreview');
            const previewImg = document.getElementById('logoPreviewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Prévisualisation de la bannière
        function previewBanner(input) {
            const previewContainer = document.getElementById('bannerPreviewContainer');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContainer.innerHTML = `<img src="${e.target.result}" alt="Nouvelle bannière" style="width: 100%; height: 100%; object-fit: cover;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Gestion des vacances dans le calendrier académique
        const vacations = {
            term1: <?php echo json_encode($academic_calendar['trimestre1']['vacations']); ?>,
            term2: <?php echo json_encode($academic_calendar['trimestre2']['vacations']); ?>,
            term3: <?php echo json_encode($academic_calendar['trimestre3']['vacations']); ?>
        };
        
        function renderVacations(term) {
            const list = document.getElementById(`${term}_vacations_list`);
            const hiddenInput = document.getElementById(`${term}_vacations`);
            list.innerHTML = '';
            
            vacations[term].forEach((vacation, index) => {
                const div = document.createElement('div');
                div.className = 'vacation-item';
                div.innerHTML = `
                    <div>
                        <div class="vacation-label">${vacation.label}</div>
                        <div class="vacation-dates">${vacation.start} au ${vacation.end}</div>
                    </div>
                    <button type="button" class="btn-danger" onclick="removeVacation('${term}', ${index})" style="padding: 5px 10px; font-size: 0.8rem;">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                list.appendChild(div);
            });
            
            hiddenInput.value = JSON.stringify(vacations[term]);
        }
        
        function addVacation(term) {
            const start = prompt('Date de début (YYYY-MM-DD):');
            if (!start) return;
            
            const end = prompt('Date de fin (YYYY-MM-DD):');
            if (!end) return;
            
            const label = prompt('Libellé de la vacance:');
            if (!label) return;
            
            vacations[term].push({
                start: start,
                end: end,
                label: label
            });
            
            renderVacations(term);
        }
        
        function removeVacation(term, index) {
            if (confirm('Supprimer cette période de vacances ?')) {
                vacations[term].splice(index, 1);
                renderVacations(term);
            }
        }
        
        // Initialiser les vacances
        renderVacations('term1');
        renderVacations('term2');
        renderVacations('term3');
        
        // Actions système
        function clearCache() {
            if (confirm('Vider le cache système ?')) {
                fetch('clear-cache.php')
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message || 'Cache vidé avec succès');
                    })
                    .catch(error => {
                        alert('Erreur: ' + error.message);
                    });
            }
        }
        
        function exportData() {
            if (confirm('Exporter toutes les données de l\'école ?')) {
                window.location.href = 'export-data.php?type=all';
            }
        }
        
        function testEmail() {
            const email = prompt('Entrez l\'email de test:');
            if (email) {
                fetch('test-email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email: email })
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message || 'Email de test envoyé');
                })
                .catch(error => {
                    alert('Erreur: ' + error.message);
                });
            }
        }
        
        function resetToDefault() {
            if (confirm('Restaurer toutes les valeurs par défaut ? Cette action est irréversible.')) {
                document.getElementById('configForm').reset();
                updateColorDisplays();
                
                // Réinitialiser les vacances
                const currentYear = new Date().getFullYear();
                vacations.term1 = [
                    { start: currentYear + '-10-28', end: currentYear + '-11-03', label: 'Toussaint' },
                    { start: currentYear + '-12-21', end: (currentYear + 1) + '-01-05', label: 'Noël' }
                ];
                vacations.term2 = [
                    { start: (currentYear + 1) + '-02-17', end: (currentYear + 1) + '-02-23', label: 'Carnaval' }
                ];
                vacations.term3 = [
                    { start: (currentYear + 1) + '-04-14', end: (currentYear + 1) + '-04-20', label: 'Pâques' }
                ];
                
                renderVacations('term1');
                renderVacations('term2');
                renderVacations('term3');
                
                // Réinitialiser les dates des trimestres
                document.querySelector('input[name="term1_start"]').value = currentYear + '-09-02';
                document.querySelector('input[name="term1_end"]').value = currentYear + '-12-20';
                document.querySelector('input[name="term2_start"]').value = (currentYear + 1) + '-01-06';
                document.querySelector('input[name="term2_end"]').value = (currentYear + 1) + '-03-28';
                document.querySelector('input[name="term3_start"]').value = (currentYear + 1) + '-03-31';
                document.querySelector('input[name="term3_end"]').value = (currentYear + 1) + '-06-30';
                
                alert('Valeurs par défaut restaurées');
            }
        }
        
        // Validation du formulaire
        document.getElementById('configForm').addEventListener('submit', function(e) {
            const schoolName = document.getElementById('school_name').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!schoolName) {
                e.preventDefault();
                alert('Le nom de l\'école est obligatoire');
                document.getElementById('school_name').focus();
                return false;
            }
            
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                e.preventDefault();
                alert('Veuillez saisir un email valide');
                document.getElementById('email').focus();
                return false;
            }
            
            return true;
        });
        
        // Initialiser l'aperçu
        updateColorDisplays();
    </script>
</body>
</html>