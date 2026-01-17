<?php
// dashboard/school/settings/appearance.php
require_once '../../../includes/config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Location: ../../');
    exit();
}

$user_id = $_SESSION['user_id'];
$school_id = $_SESSION['school_id'];

// Récupérer les informations de l'école
$stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $primary_color = sanitize($_POST['primary_color'] ?? '#009543');
    $secondary_color = sanitize($_POST['secondary_color'] ?? '#002B7F');
    $accent_color = sanitize($_POST['accent_color'] ?? '#FBDE4A');
    $currency = sanitize($_POST['currency'] ?? 'FCFA');
    $currency_symbol = sanitize($_POST['currency_symbol'] ?? 'FCFA');
    
    // Gestion du logo
    $logo = $school['logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $uploadDir = '../../../assets/uploads/schools/logos/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $logo_filename = 'logo_' . $school_id . '_' . time() . '.png';
        $targetFile = $uploadDir . $logo_filename;
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
            $logo = '/assets/uploads/schools/logos/' . $logo_filename;
        }
    }
    
    // Gestion de la bannière
    $banner = $school['banner'];
    if (isset($_FILES['banner']) && $_FILES['banner']['error'] == 0) {
        $uploadDir = '../../../assets/uploads/schools/banners/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $banner_filename = 'banner_' . $school_id . '_' . time() . '.jpg';
        $targetFile = $uploadDir . $banner_filename;
        
        if (move_uploaded_file($_FILES['banner']['tmp_name'], $targetFile)) {
            $banner = '/assets/uploads/schools/banners/' . $banner_filename;
        }
    }
    
    // Mise à jour en base
    $stmt = $pdo->prepare("
        UPDATE schools SET 
            primary_color = ?,
            secondary_color = ?,
            accent_color = ?,
            logo = ?,
            banner = ?,
            currency = ?,
            currency_symbol = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([
        $primary_color, $secondary_color, $accent_color,
        $logo, $banner, $currency, $currency_symbol,
        $school_id
    ])) {
        // Générer le CSS personnalisé
        generateSchoolCSS($school_id);
        
        $success = "Apparence mise à jour avec succès !";
        $school = $pdo->prepare("SELECT * FROM schools WHERE id = ?")->execute([$school_id])->fetch();
    } else {
        $error = "Erreur lors de la mise à jour.";
    }
}

// Inclure la sidebar personnalisée
include '../school-sidebar.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnalisation - <?php echo $school['school_name']; ?></title>
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <!-- CSS personnalisé de l'école -->
    <link rel="stylesheet" href="<?php echo generateSchoolCSS($school_id); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .color-picker-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .color-picker-item {
            text-align: center;
        }
        
        .color-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 15px;
            border: 3px solid #ddd;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .color-preview:hover {
            transform: scale(1.1);
        }
        
        .upload-preview {
            width: 200px;
            height: 150px;
            border: 2px dashed #ddd;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px auto;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        .upload-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .currency-selector {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .currency-option {
            padding: 15px 25px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .currency-option:hover {
            border-color: var(--primary-color);
            background: rgba(var(--primary-color-rgb), 0.1);
        }
        
        .currency-option.selected {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
        
        .live-preview {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-top: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .preview-header {
            background: var(--primary-color);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .preview-button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            margin: 10px 5px;
        }
        
        .preview-secondary {
            background: var(--secondary-color);
            color: white;
        }
        
        .preview-accent {
            background: var(--accent-color);
            color: #333;
        }
    </style>
</head>
<body class="dashboard">
    <?php include '../school-sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher...">
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['email']; ?></span>
                <img src="../../../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">
                <i class="fas fa-palette"></i> Personnalisation de l'école
            </h1>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" id="appearanceForm">
                <div class="form-section">
                    <h3><i class="fas fa-paint-brush"></i> Couleurs de l'école</h3>
                    <p>Personnalisez les couleurs principales de votre école</p>
                    
                    <div class="color-picker-grid">
                        <div class="color-picker-item">
                            <div class="color-preview" id="primaryColorPreview" 
                                 style="background: <?php echo $school['primary_color']; ?>"
                                 onclick="document.getElementById('primary_color').click()"></div>
                            <label>Couleur principale</label>
                            <input type="color" id="primary_color" name="primary_color" 
                                   value="<?php echo $school['primary_color']; ?>"
                                   onchange="updateColorPreview('primaryColorPreview', this.value)">
                        </div>
                        
                        <div class="color-picker-item">
                            <div class="color-preview" id="secondaryColorPreview" 
                                 style="background: <?php echo $school['secondary_color']; ?>"
                                 onclick="document.getElementById('secondary_color').click()"></div>
                            <label>Couleur secondaire</label>
                            <input type="color" id="secondary_color" name="secondary_color" 
                                   value="<?php echo $school['secondary_color']; ?>"
                                   onchange="updateColorPreview('secondaryColorPreview', this.value)">
                        </div>
                        
                        <div class="color-picker-item">
                            <div class="color-preview" id="accentColorPreview" 
                                 style="background: <?php echo $school['accent_color']; ?>"
                                 onclick="document.getElementById('accent_color').click()"></div>
                            <label>Couleur d'accent</label>
                            <input type="color" id="accent_color" name="accent_color" 
                                   value="<?php echo $school['accent_color']; ?>"
                                   onchange="updateColorPreview('accentColorPreview', this.value)">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-images"></i> Logo et Bannière</h3>
                    <p>Téléchargez votre logo et bannière pour personnaliser votre école</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Logo de l'école</label>
                            <div class="upload-preview" id="logoPreview">
                                <?php if ($school['logo']): ?>
                                    <img src="<?php echo $school['logo']; ?>" alt="Logo actuel">
                                <?php else: ?>
                                    <i class="fas fa-school fa-3x text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="logo" accept="image/*" 
                                   onchange="previewImage(this, 'logoPreview')">
                            <small>Format: PNG ou JPG, taille recommandée: 200x200px</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Bannière de l'école</label>
                            <div class="upload-preview" id="bannerPreview">
                                <?php if ($school['banner']): ?>
                                    <img src="<?php echo $school['banner']; ?>" alt="Bannière actuelle">
                                <?php else: ?>
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="banner" accept="image/*" 
                                   onchange="previewImage(this, 'bannerPreview')">
                            <small>Format: JPG, taille recommandée: 1200x400px</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-money-bill-wave"></i> Devise et Tarification</h3>
                    <p>Configurez la devise utilisée pour afficher vos prix</p>
                    
                    <div class="currency-selector">
                        <div class="currency-option <?php echo $school['currency'] == 'FCFA' ? 'selected' : ''; ?>" 
                             onclick="selectCurrency('FCFA', 'FCFA')">
                            <i class="fas fa-flag"></i>
                            <h4>Franc CFA</h4>
                            <p>FCFA</p>
                        </div>
                        
                        <div class="currency-option <?php echo $school['currency'] == 'USD' ? 'selected' : ''; ?>" 
                             onclick="selectCurrency('USD', '$')">
                            <i class="fas fa-dollar-sign"></i>
                            <h4>Dollar US</h4>
                            <p>$ USD</p>
                        </div>
                        
                        <div class="currency-option <?php echo $school['currency'] == 'EUR' ? 'selected' : ''; ?>" 
                             onclick="selectCurrency('EUR', '€')">
                            <i class="fas fa-euro-sign"></i>
                            <h4>Euro</h4>
                            <p>€ EUR</p>
                        </div>
                    </div>
                    
                    <input type="hidden" id="currency" name="currency" value="<?php echo $school['currency']; ?>">
                    <input type="hidden" id="currency_symbol" name="currency_symbol" value="<?php echo $school['currency_symbol']; ?>">
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Mode d'affichage des prix</label>
                        <select name="price_display_mode" class="form-control">
                            <option value="public" <?php echo $school['price_display_mode'] == 'public' ? 'selected' : ''; ?>>
                                Public - Tous peuvent voir les prix
                            </option>
                            <option value="private" <?php echo $school['price_display_mode'] == 'private' ? 'selected' : ''; ?>>
                                Privé - Seulement pour les parents connectés
                            </option>
                            <option value="on_request" <?php echo $school['price_display_mode'] == 'on_request' ? 'selected' : ''; ?>>
                                Sur demande - Contactez-nous pour les prix
                            </option>
                        </select>
                    </div>
                </div>
                
                <!-- Aperçu en direct -->
                <div class="live-preview">
                    <h3><i class="fas fa-eye"></i> Aperçu en direct</h3>
                    <p>Voici comment votre école apparaîtra aux visiteurs</p>
                    
                    <div class="preview-header">
                        <h4><?php echo $school['school_name']; ?></h4>
                        <p>Votre slogan ici</p>
                    </div>
                    
                    <div class="preview-buttons">
                        <button type="button" class="preview-button">Bouton Primaire</button>
                        <button type="button" class="preview-button preview-secondary">Bouton Secondaire</button>
                        <button type="button" class="preview-button preview-accent">Bouton Accent</button>
                    </div>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <p>Exemple de prix: <strong id="priceExample"><?php echo number_format(150000, 0, ',', ' '); ?> <?php echo $school['currency_symbol']; ?></strong></p>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetToDefaults()">
                        <i class="fas fa-redo"></i> Réinitialiser
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function updateColorPreview(previewId, color) {
        document.getElementById(previewId).style.background = color;
        
        // Mettre à jour l'aperçu en direct
        if (previewId === 'primaryColorPreview') {
            document.querySelector('.preview-header').style.background = color;
            document.querySelector('.preview-button').style.background = color;
        } else if (previewId === 'secondaryColorPreview') {
            document.querySelector('.preview-secondary').style.background = color;
        } else if (previewId === 'accentColorPreview') {
            document.querySelector('.preview-accent').style.background = color;
        }
    }
    
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        const file = input.files[0];
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" alt="Prévisualisation">`;
            }
            reader.readAsDataURL(file);
        }
    }
    
    function selectCurrency(currency, symbol) {
        document.getElementById('currency').value = currency;
        document.getElementById('currency_symbol').value = symbol;
        
        // Mettre à jour l'aperçu du prix
        const examplePrice = 150000;
        const formattedPrice = new Intl.NumberFormat('fr-FR').format(examplePrice);
        document.getElementById('priceExample').textContent = `${formattedPrice} ${symbol}`;
        
        // Mettre à jour la sélection visuelle
        document.querySelectorAll('.currency-option').forEach(option => {
            option.classList.remove('selected');
        });
        event.currentTarget.classList.add('selected');
    }
    
    function resetToDefaults() {
        if (confirm('Êtes-vous sûr de vouloir réinitialiser à la configuration par défaut ?')) {
            document.getElementById('primary_color').value = '#009543';
            document.getElementById('secondary_color').value = '#002B7F';
            document.getElementById('accent_color').value = '#FBDE4A';
            document.getElementById('currency').value = 'FCFA';
            document.getElementById('currency_symbol').value = 'FCFA';
            
            updateColorPreview('primaryColorPreview', '#009543');
            updateColorPreview('secondaryColorPreview', '#002B7F');
            updateColorPreview('accentColorPreview', '#FBDE4A');
            selectCurrency('FCFA', 'FCFA');
        }
    }
    </script>
</body>
</html>